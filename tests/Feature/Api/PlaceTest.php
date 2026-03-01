<?php

namespace Tests\Feature\Api;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // =========================================================================
    // Index
    // =========================================================================

    public function test_index_returns_paginated_places(): void
    {
        Place::factory()->count(5)->create();

        $this->actingAs($this->user, 'sanctum')
             ->getJson('/api/places')
             ->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonStructure(['data' => ['data', 'total', 'per_page', 'current_page']]);
    }

    public function test_index_returns_401_without_token(): void
    {
        $this->getJson('/api/places')->assertStatus(401);
    }

    public function test_index_filters_by_city(): void
    {
        Place::factory()->create(['city' => 'Seoul']);
        Place::factory()->create(['city' => 'Seoul']);
        Place::factory()->create(['city' => 'Tokyo']);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/places?city=Seoul')
                         ->assertStatus(200);

        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals('Seoul', $response->json('data.data.0.city'));
    }

    public function test_index_filters_by_country(): void
    {
        Place::factory()->count(3)->create(['country' => 'Korea']);
        Place::factory()->create(['country' => 'Japan']);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/places?country=Korea')
                         ->assertStatus(200);

        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_index_filters_by_category(): void
    {
        Place::factory()->count(2)->create(['category' => 'attraction']);
        Place::factory()->create(['category' => 'restaurant']);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/places?category=attraction')
                         ->assertStatus(200);

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_index_searches_by_name_case_insensitive(): void
    {
        Place::factory()->create(['name' => 'Gyeongbokgung Palace']);
        Place::factory()->create(['name' => 'Tokyo Tower']);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/places?search=gyeongbokgung')
                         ->assertStatus(200);

        $this->assertCount(1, $response->json('data.data'));
        $this->assertStringContainsStringIgnoringCase('gyeongbokgung', $response->json('data.data.0.name'));
    }

    public function test_index_returns_empty_when_no_match(): void
    {
        Place::factory()->count(3)->create(['city' => 'Seoul']);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/places?city=Busan')
                         ->assertStatus(200);

        $this->assertCount(0, $response->json('data.data'));
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function test_show_returns_place_detail(): void
    {
        $place = Place::factory()->create();

        $this->actingAs($this->user, 'sanctum')
             ->getJson("/api/places/{$place->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.id', $place->id)
             ->assertJsonPath('data.name', $place->name);
    }

    public function test_show_returns_404_for_missing_place(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->getJson('/api/places/99999')
             ->assertStatus(404)
             ->assertJsonPath('success', false);
    }
}
