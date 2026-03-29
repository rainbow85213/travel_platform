<?php

namespace Tests\Feature\Api;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function fakeChatbotSuccess(string $reply = '테스트 응답', ?array $itinerary = null): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => '성공',
                'data'    => [
                    'session_id' => 'user_' . $this->user->id,
                    'type'       => $itinerary ? 'itinerary' : 'message',
                    'reply'      => $reply,
                    'itinerary'  => $itinerary,
                ],
            ], 200),
        ]);
    }

    // =========================================================================
    // POST /api/chat
    // =========================================================================

    public function test_chat_returns_401_without_token(): void
    {
        $this->postJson('/api/chat', ['message' => '안녕'])
            ->assertStatus(401);
    }

    public function test_chat_sends_message_and_returns_reply(): void
    {
        $this->fakeChatbotSuccess('안녕하세요!');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/chat', ['message' => '안녕'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reply', '안녕하세요!');
    }

    public function test_chat_saves_both_messages_to_database(): void
    {
        $this->fakeChatbotSuccess('AI 응답');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/chat', ['message' => '사용자 입력']);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $this->user->id,
            'role'    => 'user',
            'text'    => '사용자 입력',
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $this->user->id,
            'role'    => 'assistant',
            'text'    => 'AI 응답',
        ]);
    }

    public function test_chat_includes_schedule_when_itinerary_returned(): void
    {
        $itinerary = [
            [
                'day'         => 1,
                'time'        => '09:00',
                'place'       => '경복궁',
                'latitude'    => 37.5796,
                'longitude'   => 126.9770,
                'description' => '조선 왕조 대표 궁궐',
            ],
        ];

        $this->fakeChatbotSuccess('일정을 만들었어요', $itinerary);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/chat', ['message' => '서울 1일 코스 만들어줘'])
            ->assertStatus(200)
            ->assertJsonPath('data.reply', '일정을 만들었어요');

        $this->assertArrayHasKey('schedule', $response->json('data'));
        $this->assertCount(1, $response->json('data.schedule'));
    }

    public function test_chat_does_not_include_schedule_key_when_no_itinerary(): void
    {
        $this->fakeChatbotSuccess('일반 응답', null);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/chat', ['message' => '날씨 알려줘'])
            ->assertStatus(200);

        $this->assertArrayNotHasKey('schedule', $response->json('data'));
    }

    public function test_chat_proxies_request_with_correct_payload(): void
    {
        $this->fakeChatbotSuccess();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/chat', ['message' => '테스트 메시지']);

        Http::assertSent(function (ClientRequest $request) {
            return str_ends_with($request->url(), '/chat')
                && $request['session_id'] === 'user_' . $this->user->id
                && $request['message']    === '테스트 메시지';
        });
    }

    public function test_chat_returns_500_when_chatbot_engine_returns_error(): void
    {
        Http::fake([
            '*/chat' => Http::response(['message' => 'Internal Server Error'], 500),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/chat', ['message' => '실패 케이스'])
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        // 내부 오류 상세가 클라이언트 응답에 노출되면 안 됨
        $this->assertStringNotContainsString('Internal Server Error', $response->json('message'));
    }

    public function test_chat_returns_500_on_connection_failure(): void
    {
        Http::fake(function (ClientRequest $request) {
            throw new ConnectionException('Connection refused');
        });

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/chat', ['message' => '연결 실패 케이스'])
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        $this->assertStringNotContainsString('Connection refused', $response->json('message'));
    }

    public function test_chat_fails_validation_when_message_missing(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/chat', [])
            ->assertStatus(422);
    }

    public function test_chat_fails_validation_when_message_exceeds_max_length(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/chat', ['message' => str_repeat('a', 1001)])
            ->assertStatus(422);
    }

    // =========================================================================
    // GET /api/chat/history
    // =========================================================================

    public function test_history_returns_401_without_token(): void
    {
        $this->getJson('/api/chat/history')->assertStatus(401);
    }

    public function test_history_returns_only_own_messages(): void
    {
        ChatMessage::factory()->count(3)->create(['user_id' => $this->user->id]);
        ChatMessage::factory()->count(2)->create(); // other user

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/chat/history')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(3, $response->json('data.messages'));
    }

    public function test_history_returns_empty_array_for_new_user(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/chat/history')
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data.messages'));
    }

    // =========================================================================
    // DELETE /api/chat/history
    // =========================================================================

    public function test_destroy_history_returns_401_without_token(): void
    {
        $this->deleteJson('/api/chat/history')->assertStatus(401);
    }

    public function test_destroy_history_deletes_only_own_messages(): void
    {
        $other = User::factory()->create();

        ChatMessage::factory()->count(3)->create(['user_id' => $this->user->id]);
        ChatMessage::factory()->count(2)->create(['user_id' => $other->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/chat/history')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('chat_messages', 2);
        $this->assertEquals(0, ChatMessage::where('user_id', $this->user->id)->count());
        $this->assertEquals(2, ChatMessage::where('user_id', $other->id)->count());
    }

    public function test_destroy_history_succeeds_when_no_messages_exist(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/chat/history')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
