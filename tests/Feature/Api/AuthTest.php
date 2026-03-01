<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Register
    // =========================================================================

    public function test_register_returns_201_with_token_and_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => '홍길동',
            'email'                 => 'hong@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure([
                     'data' => ['token', 'user' => ['id', 'name', 'email']],
                 ]);

        $this->assertDatabaseHas('users', ['email' => 'hong@example.com']);
    }

    public function test_register_fails_when_required_fields_missing(): void
    {
        $this->postJson('/api/auth/register', [])
             ->assertStatus(422)
             ->assertJsonPath('success', false)
             ->assertJsonStructure(['errors']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/auth/register', [
            'name'                  => '중복',
            'email'                 => 'dup@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_register_fails_when_password_not_confirmed(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => '홍길동',
            'email'                 => 'hong@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different',
        ])->assertStatus(422);
    }

    // =========================================================================
    // Login
    // =========================================================================

    public function test_login_returns_200_with_token_and_user(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertStatus(200)
          ->assertJsonPath('success', true)
          ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)
          ->assertJsonPath('success', false);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    // =========================================================================
    // Me
    // =========================================================================

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/auth/me')
             ->assertStatus(200)
             ->assertJsonPath('data.id', $user->id)
             ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_returns_401_without_token(): void
    {
        $this->getJson('/api/auth/me')
             ->assertStatus(401)
             ->assertJsonPath('success', false);
    }

    // =========================================================================
    // Logout
    // =========================================================================

    public function test_logout_deletes_current_token(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
             ->postJson('/api/auth/logout')
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_all_deletes_all_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('device-1');
        $user->createToken('device-2');

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/logout-all')
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
