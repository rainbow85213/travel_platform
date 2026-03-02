<?php

namespace Tests\Feature\Api;

use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItineraryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'      => '도쿄 여행',
            'start_date' => '2026-07-01',
            'end_date'   => '2026-07-07',
        ], $overrides);
    }

    // =========================================================================
    // Index
    // =========================================================================

    public function test_index_returns_only_own_itineraries(): void
    {
        Itinerary::factory()->count(3)->create(['user_id' => $this->user->id]);
        Itinerary::factory()->count(2)->create(); // other users

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/itineraries')
                         ->assertStatus(200)
                         ->assertJsonPath('success', true);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        Itinerary::factory()->create(['user_id' => $this->user->id, 'status' => 'draft']);
        Itinerary::factory()->create(['user_id' => $this->user->id, 'status' => 'published']);
        Itinerary::factory()->create(['user_id' => $this->user->id, 'status' => 'archived']);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/itineraries?status=draft')
                         ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('draft', $response->json('data.0.status'));
    }

    public function test_index_returns_401_without_token(): void
    {
        $this->getJson('/api/itineraries')->assertStatus(401);
    }

    // =========================================================================
    // Store
    // =========================================================================

    public function test_store_creates_itinerary_with_draft_status(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->postJson('/api/itineraries', $this->validPayload())
             ->assertStatus(201)
             ->assertJsonPath('data.title', '도쿄 여행')
             ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('itineraries', [
            'user_id' => $this->user->id,
            'title'   => '도쿄 여행',
        ]);
    }

    public function test_store_accepts_custom_status(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->postJson('/api/itineraries', $this->validPayload(['status' => 'published']))
             ->assertStatus(201)
             ->assertJsonPath('data.status', 'published');
    }

    public function test_store_fails_when_title_missing(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->postJson('/api/itineraries', [
                 'start_date' => '2026-07-01',
                 'end_date'   => '2026-07-07',
             ])
             ->assertStatus(422);
    }

    public function test_store_fails_when_end_date_before_start_date(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->postJson('/api/itineraries', $this->validPayload([
                 'start_date' => '2026-07-10',
                 'end_date'   => '2026-07-01',
             ]))
             ->assertStatus(422);
    }

    public function test_store_fails_with_invalid_status(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->postJson('/api/itineraries', $this->validPayload(['status' => 'invalid']))
             ->assertStatus(422);
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function test_show_returns_itinerary_with_items_relation(): void
    {
        $itinerary = Itinerary::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
             ->getJson("/api/itineraries/{$itinerary->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.id', $itinerary->id)
             ->assertJsonPath('data.title', $itinerary->title)
             ->assertJsonStructure(['data' => ['items']]);
    }

    public function test_show_returns_403_for_other_users_itinerary(): void
    {
        $itinerary = Itinerary::factory()->create(); // other user

        $this->actingAs($this->user, 'sanctum')
             ->getJson("/api/itineraries/{$itinerary->id}")
             ->assertStatus(403)
             ->assertJsonPath('success', false);
    }

    public function test_show_returns_404_for_missing_itinerary(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->getJson('/api/itineraries/99999')
             ->assertStatus(404);
    }

    // =========================================================================
    // Update
    // =========================================================================

    public function test_update_modifies_title_and_status(): void
    {
        $itinerary = Itinerary::factory()->create(['user_id' => $this->user->id, 'status' => 'draft']);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/itineraries/{$itinerary->id}", [
                 'title'  => '수정된 제목',
                 'status' => 'published',
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.title', '수정된 제목')
             ->assertJsonPath('data.status', 'published');
    }

    public function test_update_returns_403_for_other_users_itinerary(): void
    {
        $itinerary = Itinerary::factory()->create(); // other user

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/itineraries/{$itinerary->id}", ['title' => '해킹 시도'])
             ->assertStatus(403);
    }

    // =========================================================================
    // Destroy
    // =========================================================================

    public function test_destroy_soft_deletes_itinerary(): void
    {
        $itinerary = Itinerary::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
             ->deleteJson("/api/itineraries/{$itinerary->id}")
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertSoftDeleted('itineraries', ['id' => $itinerary->id]);
    }

    public function test_destroy_returns_403_for_other_users_itinerary(): void
    {
        $itinerary = Itinerary::factory()->create(); // other user

        $this->actingAs($this->user, 'sanctum')
             ->deleteJson("/api/itineraries/{$itinerary->id}")
             ->assertStatus(403);
    }

    // =========================================================================
    // SaveFromChatbot
    // =========================================================================

    private function chatbotPayload(array $overrides = []): array
    {
        return array_merge([
            'title'      => '제주도 1박 2일',
            'start_date' => '2026-04-01',
            'itinerary'  => [
                [
                    'day'         => 1,
                    'time'        => '09:00',
                    'place'       => '성산일출봉',
                    'latitude'    => 33.4589,
                    'longitude'   => 126.9425,
                    'description' => '유네스코 세계자연유산',
                ],
                [
                    'day'         => 1,
                    'time'        => '14:00',
                    'place'       => '만장굴',
                    'latitude'    => 33.5283,
                    'longitude'   => 126.7714,
                    'description' => '용암동굴',
                ],
                [
                    'day'         => 2,
                    'time'        => '10:00',
                    'place'       => '협재해수욕장',
                    'latitude'    => 33.3941,
                    'longitude'   => 126.2394,
                    'description' => '에메랄드빛 해변',
                ],
            ],
        ], $overrides);
    }

    public function test_save_from_chatbot_creates_itinerary_and_items(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload())
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', '제주도 1박 2일')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.start_date', '2026-04-01T00:00:00.000000Z')
            ->assertJsonPath('data.end_date', '2026-04-02T00:00:00.000000Z');

        $this->assertCount(3, $response->json('data.items'));
        $this->assertDatabaseHas('itineraries', [
            'user_id'    => $this->user->id,
            'title'      => '제주도 1박 2일',
            'start_date' => '2026-04-01',
            'end_date'   => '2026-04-02',
        ]);
        $this->assertDatabaseCount('itinerary_items', 3);
    }

    public function test_save_from_chatbot_response_includes_place_relation(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload())
            ->assertStatus(201);

        $items = $response->json('data.items');
        foreach ($items as $item) {
            $this->assertArrayHasKey('place', $item);
            $this->assertNotNull($item['place']);
        }
    }

    public function test_save_from_chatbot_matches_existing_place_by_name(): void
    {
        $existing = Place::factory()->create(['name' => '성산일출봉']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload());

        // 새 Place가 생성되지 않고 기존 Place가 재사용되어야 함
        $this->assertDatabaseHas('itinerary_items', ['place_id' => $existing->id]);
        $this->assertDatabaseCount('places', 3); // 성산일출봉(기존) + 만장굴(신규) + 협재해수욕장(신규)
    }

    public function test_save_from_chatbot_creates_new_place_when_not_found(): void
    {
        $this->assertDatabaseCount('places', 0);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload())
            ->assertStatus(201);

        $this->assertDatabaseCount('places', 3);
        $this->assertDatabaseHas('places', [
            'name'      => '성산일출봉',
            'latitude'  => 33.4589000,
            'longitude' => 126.9425000,
        ]);
    }

    public function test_save_from_chatbot_calculates_visited_at_correctly(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload())
            ->assertStatus(201);

        // 1일차 09:00 → 2026-04-01 09:00:00
        $this->assertDatabaseHas('itinerary_items', [
            'day_number' => 1,
            'visited_at' => '2026-04-01 09:00:00',
        ]);
        // 1일차 14:00 → 2026-04-01 14:00:00
        $this->assertDatabaseHas('itinerary_items', [
            'day_number' => 1,
            'visited_at' => '2026-04-01 14:00:00',
        ]);
        // 2일차 10:00 → 2026-04-02 10:00:00
        $this->assertDatabaseHas('itinerary_items', [
            'day_number' => 2,
            'visited_at' => '2026-04-02 10:00:00',
        ]);
    }

    public function test_save_from_chatbot_assigns_sort_order_within_day(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload())
            ->assertStatus(201);

        $items = collect($response->json('data.items'));

        $day1Items = $items->where('day_number', 1)->values();
        $this->assertEquals(0, $day1Items[0]['sort_order']);
        $this->assertEquals(1, $day1Items[1]['sort_order']);

        $day2Items = $items->where('day_number', 2)->values();
        $this->assertEquals(0, $day2Items[0]['sort_order']); // 2일차는 0부터 재시작
    }

    public function test_save_from_chatbot_uses_today_when_start_date_omitted(): void
    {
        $payload = $this->chatbotPayload();
        unset($payload['start_date']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $payload)
            ->assertStatus(201);

        $savedStartDate = substr($response->json('data.start_date'), 0, 10);
        $this->assertEquals(today()->toDateString(), $savedStartDate);
    }

    public function test_save_from_chatbot_generates_default_title_when_omitted(): void
    {
        $payload = $this->chatbotPayload();
        unset($payload['title']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $payload)
            ->assertStatus(201);

        $this->assertStringStartsWith('챗봇 일정', $response->json('data.title'));
    }

    public function test_save_from_chatbot_stores_description_as_notes(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload())
            ->assertStatus(201);

        $this->assertDatabaseHas('itinerary_items', ['notes' => '유네스코 세계자연유산']);
        $this->assertDatabaseHas('itinerary_items', ['notes' => '용암동굴']);
    }

    public function test_save_from_chatbot_returns_401_without_token(): void
    {
        $this->postJson('/api/itineraries/save', $this->chatbotPayload())
            ->assertStatus(401);
    }

    public function test_save_from_chatbot_fails_when_itinerary_missing(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', ['title' => '제목만'])
            ->assertStatus(422);
    }

    public function test_save_from_chatbot_fails_when_itinerary_empty(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload(['itinerary' => []]))
            ->assertStatus(422);
    }

    public function test_save_from_chatbot_fails_when_item_fields_missing(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload([
                'itinerary' => [
                    ['day' => 1, 'time' => '09:00'], // place, latitude, longitude 누락
                ],
            ]))
            ->assertStatus(422);
    }

    public function test_save_from_chatbot_fails_with_invalid_time_format(): void
    {
        $item           = $this->chatbotPayload()['itinerary'][0];
        $item['time']   = '9시'; // 잘못된 형식

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload(['itinerary' => [$item]]))
            ->assertStatus(422);
    }

    public function test_save_from_chatbot_fails_with_invalid_coordinates(): void
    {
        $item              = $this->chatbotPayload()['itinerary'][0];
        $item['latitude']  = 999; // 범위 초과

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/itineraries/save', $this->chatbotPayload(['itinerary' => [$item]]))
            ->assertStatus(422);
    }
}
