<?php

namespace Tests\Feature\Api;

use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItineraryItemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Itinerary $itinerary;
    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user      = User::factory()->create();
        $this->itinerary = Itinerary::factory()->create(['user_id' => $this->user->id]);
        $this->place     = Place::factory()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'place_id'   => $this->place->id,
            'day_number' => 1,
            'sort_order' => 0,
        ], $overrides);
    }

    // =========================================================================
    // Index
    // =========================================================================

    public function test_index_returns_items_with_place_relation(): void
    {
        ItineraryItem::factory()->count(3)->create([
            'itinerary_id' => $this->itinerary->id,
            'place_id'     => $this->place->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson("/api/itineraries/{$this->itinerary->id}/items")
                         ->assertStatus(200)
                         ->assertJsonPath('success', true);

        $this->assertCount(3, $response->json('data'));
        $this->assertNotNull($response->json('data.0.place'));
    }

    public function test_index_returns_403_for_other_users_itinerary(): void
    {
        $otherItinerary = Itinerary::factory()->create(); // other user

        $this->actingAs($this->user, 'sanctum')
             ->getJson("/api/itineraries/{$otherItinerary->id}/items")
             ->assertStatus(403);
    }

    public function test_index_returns_401_without_token(): void
    {
        $this->getJson("/api/itineraries/{$this->itinerary->id}/items")
             ->assertStatus(401);
    }

    // =========================================================================
    // Store
    // =========================================================================

    public function test_store_adds_item_to_itinerary(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->postJson("/api/itineraries/{$this->itinerary->id}/items", $this->validPayload([
                 'duration_minutes' => 120,
                 'notes'            => '꼭 가봐야 할 곳',
             ]))
             ->assertStatus(201)
             ->assertJsonPath('data.day_number', 1)
             ->assertJsonPath('data.duration_minutes', 120)
             ->assertJsonStructure(['data' => ['place']]);

        $this->assertDatabaseHas('itinerary_items', [
            'itinerary_id' => $this->itinerary->id,
            'place_id'     => $this->place->id,
        ]);
    }

    public function test_store_fails_when_place_id_missing(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->postJson("/api/itineraries/{$this->itinerary->id}/items", [
                 'day_number' => 1,
             ])
             ->assertStatus(422);
    }

    public function test_store_fails_when_place_not_exists(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->postJson("/api/itineraries/{$this->itinerary->id}/items", [
                 'place_id'   => 99999,
                 'day_number' => 1,
             ])
             ->assertStatus(422);
    }

    public function test_store_returns_403_for_other_users_itinerary(): void
    {
        $otherItinerary = Itinerary::factory()->create(); // other user

        $this->actingAs($this->user, 'sanctum')
             ->postJson("/api/itineraries/{$otherItinerary->id}/items", $this->validPayload())
             ->assertStatus(403);
    }

    // =========================================================================
    // Update
    // =========================================================================

    public function test_update_modifies_item_fields(): void
    {
        $item = ItineraryItem::factory()->create([
            'itinerary_id' => $this->itinerary->id,
            'place_id'     => $this->place->id,
            'day_number'   => 1,
        ]);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/itineraries/{$this->itinerary->id}/items/{$item->id}", [
                 'day_number'       => 3,
                 'duration_minutes' => 90,
                 'notes'            => '수정된 메모',
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.day_number', 3)
             ->assertJsonPath('data.duration_minutes', 90)
             ->assertJsonStructure(['data' => ['place']]);
    }

    public function test_update_returns_403_for_other_users_itinerary(): void
    {
        $other          = User::factory()->create();
        $otherItinerary = Itinerary::factory()->create(['user_id' => $other->id]);
        $item           = ItineraryItem::factory()->create([
            'itinerary_id' => $otherItinerary->id,
            'place_id'     => $this->place->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/itineraries/{$otherItinerary->id}/items/{$item->id}", ['day_number' => 2])
             ->assertStatus(403);
    }

    public function test_update_returns_403_when_item_does_not_belong_to_itinerary(): void
    {
        $otherItinerary = Itinerary::factory()->create(['user_id' => $this->user->id]);
        $item           = ItineraryItem::factory()->create([
            'itinerary_id' => $otherItinerary->id,
            'place_id'     => $this->place->id,
        ]);

        // item belongs to otherItinerary, not $this->itinerary → 403
        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/itineraries/{$this->itinerary->id}/items/{$item->id}", ['day_number' => 2])
             ->assertStatus(403);
    }

    // =========================================================================
    // Destroy
    // =========================================================================

    public function test_destroy_removes_item(): void
    {
        $item = ItineraryItem::factory()->create([
            'itinerary_id' => $this->itinerary->id,
            'place_id'     => $this->place->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
             ->deleteJson("/api/itineraries/{$this->itinerary->id}/items/{$item->id}")
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('itinerary_items', ['id' => $item->id]);
    }

    public function test_destroy_returns_403_for_other_users_item(): void
    {
        $other          = User::factory()->create();
        $otherItinerary = Itinerary::factory()->create(['user_id' => $other->id]);
        $item           = ItineraryItem::factory()->create([
            'itinerary_id' => $otherItinerary->id,
            'place_id'     => $this->place->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
             ->deleteJson("/api/itineraries/{$otherItinerary->id}/items/{$item->id}")
             ->assertStatus(403);
    }
}
