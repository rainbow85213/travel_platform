<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class ChatController extends Controller
{
    use ApiResponse;

    private const SYSTEM_PROMPT = <<<PROMPT
        당신은 Plango의 AI 여행 플래너입니다.
        사용자의 여행 계획 수립을 도와주는 친절하고 전문적인 어시스턴트입니다.
        여행지 추천, 일정 구성, 교통 및 숙박 정보, 현지 음식과 문화 등 여행과 관련된 모든 질문에 답해주세요.
        답변은 한국어로 작성하고, 간결하면서도 유용한 정보를 제공하세요.
        PROMPT;

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message'  => ['required', 'string', 'max:1000'],
            'history'  => ['sometimes', 'array'],
            'history.*.role'    => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string'],
        ]);

        $messages = [
            ['role' => 'system', 'content' => trim(self::SYSTEM_PROMPT)],
        ];

        // 이전 대화 이력 포함 (멀티턴 지원)
        foreach ($request->input('history', []) as $turn) {
            $messages[] = [
                'role'    => $turn['role'],
                'content' => $turn['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $request->input('message')];

        try {
            $response = OpenAI::chat()->create([
                'model'       => config('openai.model', 'gpt-4o-mini'),
                'messages'    => $messages,
                'max_tokens'  => 1024,
                'temperature' => 0.7,
            ]);

            $reply = $response->choices[0]->message->content;

            return $this->success(['reply' => $reply], '응답 성공');

        } catch (\Exception $e) {
            return $this->failure('AI 응답 생성에 실패했습니다: ' . $e->getMessage(), 500);
        }
    }
}
