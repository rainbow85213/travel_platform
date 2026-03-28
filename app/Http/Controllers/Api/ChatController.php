<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ChatController extends Controller
{
    use ApiResponse;

    // 히스토리에서 OpenAI로 전달할 최대 메시지 수 (토큰 절약)
    private const HISTORY_LIMIT = 20;

    private const SYSTEM_PROMPT = <<<'PROMPT'
당신은 Plango의 AI 여행 플래너입니다.
사용자의 여행 계획 수립을 도와주는 친절하고 전문적인 어시스턴트입니다.
여행지 추천, 일정 구성, 교통 및 숙박 정보, 현지 음식과 문화 등 여행과 관련된 모든 질문에 답해주세요.

**응답은 반드시 아래 JSON 형식으로만 출력하세요.**

일반 질문 응답 (날씨, 정보 안내 등 일정 추천이 아닐 때):
{
  "reply": "한국어 응답 텍스트",
  "schedule": null
}

여행 일정을 추천할 때:
{
  "reply": "일정 요약을 포함한 한국어 응답 텍스트",
  "schedule": [
    {
      "id": "item-1",
      "title": "장소명",
      "latitude": 37.1234,
      "longitude": 127.1234,
      "status": "pending",
      "time": "09:00",
      "scheduledAt": "YYYY-MM-DDTHH:mm:ssZ",
      "category": "attraction",
      "description": "장소 설명",
      "order": 1
    }
  ]
}

**일정 작성 규칙:**
1. 시간 순서대로 구성 (오전 → 오후 → 저녁)
2. 각 장소는 실존하는 장소이며 정확한 위경도 좌표 포함 (지도 표시용)
3. category는 반드시 "restaurant" | "attraction" | "accommodation" | "transport" | "other" 중 하나
4. status는 항상 "pending"
5. 하루 일정 기준 5~8개 항목 권장
6. 사용자가 날짜를 언급하면 scheduledAt에 해당 날짜 사용, 없으면 오늘 날짜 기준으로 작성
7. reply 텍스트는 반드시 날짜별로 상세하게 작성하세요. 예시:
   "도쿄 1박 2일 일정을 준비했어요! 😊

   📅 첫째 날
   - 09:00 신주쿠 교엔 — 넓은 정원에서 여행 시작
   - 12:00 이치란 라멘 — 진한 돈코츠 라멘으로 점심
   - 14:00 메이지 신궁 — 도심 속 고즈넉한 신사
   ...

   📅 둘째 날
   - 09:00 아사쿠사 센소지 — 도쿄의 대표 불교 사원
   ..."
   각 장소마다 시간과 간단한 설명을 포함하고, 이동 팁이나 추천 포인트도 자연스럽게 넣어주세요.

JSON 이외의 텍스트는 절대 출력하지 마세요.
PROMPT;

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // DB에서 최근 대화 이력 로드
        $history = ChatMessage::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $messages = [
            ['role' => 'system', 'content' => trim(self::SYSTEM_PROMPT)],
        ];

        foreach ($history as $msg) {
            $messages[] = [
                'role'    => $msg->role,
                'content' => $msg->role === 'assistant'
                    ? json_encode(['reply' => $msg->text, 'schedule' => $msg->schedule], JSON_UNESCAPED_UNICODE)
                    : $msg->text,
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $request->input('message')];

        try {
            $response = OpenAI::chat()->create([
                'model'           => config('openai.model', 'gpt-4o-mini'),
                'messages'        => $messages,
                'max_tokens'      => 2048,
                'temperature'     => 0.7,
                'response_format' => ['type' => 'json_object'],
            ]);

            $raw = $response->choices[0]->message->content;
            $parsed = json_decode($raw, true);

            $reply    = $parsed['reply'] ?? $raw;
            $schedule = $parsed['schedule'] ?? null;

            // 빈 배열이면 null 처리
            if (is_array($schedule) && count($schedule) === 0) {
                $schedule = null;
            }

            // 사용자 메시지 저장
            ChatMessage::create([
                'user_id'  => $user->id,
                'role'     => 'user',
                'text'     => $request->input('message'),
                'schedule' => null,
            ]);

            // AI 응답 저장
            ChatMessage::create([
                'user_id'  => $user->id,
                'role'     => 'assistant',
                'text'     => $reply,
                'schedule' => $schedule,
            ]);

            $data = ['reply' => $reply];
            if ($schedule !== null) {
                $data['schedule'] = $schedule;
            }

            return $this->success($data, '응답 성공');

        } catch (\Exception $e) {
            Log::error('OpenAI 호출 실패', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return $this->failure('AI 응답 생성에 일시적인 오류가 발생했습니다.', 500);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'limit'  => ['sometimes', 'integer', 'min:1', 'max:200'],
            'before' => ['sometimes', 'date'],
        ]);

        $limit = $request->integer('limit', 50);
        $user  = $request->user();

        $query = ChatMessage::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('before')) {
            $query->where('created_at', '<', $request->input('before'));
        }

        // limit+1 조회로 hasMore 판단
        $rows = $query->limit($limit + 1)->get();

        $hasMore  = $rows->count() > $limit;
        $messages = $rows->take($limit)->reverse()->values()->map(fn ($msg) => [
            'id'        => (string) $msg->id,
            'role'      => $msg->role,
            'text'      => $msg->text,
            'schedule'  => $msg->schedule,
            'createdAt' => $msg->created_at->toIso8601String(),
        ]);

        return $this->success([
            'messages' => $messages,
            'hasMore'  => $hasMore,
        ], '채팅 기록 조회 성공');
    }

    public function destroyHistory(Request $request): JsonResponse
    {
        ChatMessage::where('user_id', $request->user()->id)->delete();

        return $this->success(null, '채팅 기록이 삭제되었습니다.');
    }
}
