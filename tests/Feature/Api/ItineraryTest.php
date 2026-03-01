<?php

namespace Tests\Feature\Api;

use App\Models\Itinerary;
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
}
