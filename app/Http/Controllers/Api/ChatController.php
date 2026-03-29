<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    use ApiResponse;

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        try {
            $response = Http::withToken(config('services.chatbot_engine.token'))
                ->timeout(60)
                ->post(config('services.chatbot_engine.url') . '/chat', [
                    'session_id' => 'user_' . $user->id,
                    'message'    => $request->input('message'),
                ]);

            if (! $response->successful()) {
                Log::error('chatbot-engine 호출 실패', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'user_id' => $user->id,
                ]);

                return $this->failure('AI 응답 생성에 일시적인 오류가 발생했습니다.', 500);
            }

            $data  = $response->json('data', []);
            $reply = $data['reply'] ?? '';

            // chatbot-engine은 일정 확정 시 'itinerary' 키로 반환
            // 필드 구조: {day, time, place, latitude, longitude, description}
            $schedule = $data['itinerary'] ?? null;

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

            $responseData = ['reply' => $reply];
            if ($schedule !== null) {
                $responseData['schedule'] = $schedule;
            }

            return $this->success($responseData, '응답 성공');

        } catch (\Exception $e) {
            Log::error('chatbot-engine 호출 실패', ['error' => $e->getMessage(), 'user_id' => $user->id]);

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
