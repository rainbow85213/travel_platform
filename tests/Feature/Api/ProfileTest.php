<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Show
    // =========================================================================

    public function test_show_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/profile')
             ->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonPath('data.id', $user->id)
             ->assertJsonPath('data.email', $user->email);
    }

    public function test_show_returns_401_without_token(): void
    {
        $this->getJson('/api/profile')->assertStatus(401);
    }

    // =========================================================================
    // Update
    // =========================================================================

    public function test_update_name_and_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->putJson('/api/profile', [
                 'name'  => '수정된 이름',
                 'email' => 'updated@example.com',
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.name', '수정된 이름')
             ->assertJsonPath('data.email', 'updated@example.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => '수정된 이름']);
    }

    public function test_update_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->putJson('/api/profile', ['email' => 'taken@example.com'])
             ->assertStatus(422);
    }

    public function test_update_allows_own_email_unchanged(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $this->actingAs($user, 'sanctum')
             ->putJson('/api/profile', ['email' => 'mine@example.com'])
             ->assertStatus(200);
    }

    // =========================================================================
    // Password
    // =========================================================================

    public function test_update_password_success(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->putJson('/api/profile/password', [
                 'current_password'      => 'password',
                 'password'              => 'newpassword123',
                 'password_confirmation' => 'newpassword123',
             ])
             ->assertStatus(200)
             ->assertJsonPath('success', true);
    }

    public function test_update_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->putJson('/api/profile/password', [
                 'current_password'      => 'wrong-password',
                 'password'              => 'newpassword123',
                 'password_confirmation' => 'newpassword123',
             ])
             ->assertStatus(422);
    }

    public function test_update_password_fails_when_not_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->putJson('/api/profile/password', [
                 'current_password'      => 'password',
                 'password'              => 'newpassword123',
                 'password_confirmation' => 'different',
             ])
             ->assertStatus(422);
    }

    // =========================================================================
    // Preferences
    // =========================================================================

    public function test_show_preferences_auto_creates_record_if_not_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/profile/preferences')
             ->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonStructure([
                 'data' => ['language', 'currency', 'timezone', 'notification_enabled'],
             ]);

        $this->assertDatabaseHas('user_preferences', ['user_id' => $user->id]);
    }

    public function test_update_preferences_saves_all_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->putJson('/api/profile/preferences', [
                 'language'             => 'en',
                 'currency'             => 'USD',
                 'timezone'             => 'America/New_York',
                 'notification_enabled' => false,
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.language', 'en')
             ->assertJsonPath('data.currency', 'USD')
             ->assertJsonPath('data.timezone', 'America/New_York')
             ->assertJsonPath('data.notification_enabled', false);

        $this->assertDatabaseHas('user_preferences', [
            'user_id'  => $user->id,
            'language' => 'en',
            'currency' => 'USD',
        ]);
    }

    public function test_update_preferences_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->putJson('/api/profile/preferences', ['language' => 'ja']);

        $this->actingAs($user, 'sanctum')
             ->putJson('/api/profile/preferences', ['language' => 'en'])
             ->assertStatus(200)
             ->assertJsonPath('data.language', 'en');

        $this->assertDatabaseCount('user_preferences', 1);
    }
}
