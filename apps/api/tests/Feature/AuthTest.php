<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'supersecret10',
            'password_confirmation' => 'supersecret10',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'timezone']])
            ->assertJsonPath('user.email', 'ada@example.com');

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_requires_a_unique_email_and_strong_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_a_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'supersecret10',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'supersecret10',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'user']);

        $token = $login->json('token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // The token itself must be gone, so any later request is unauthorized.
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);

        // Drop the guard's cached user so the next request re-resolves the token.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_login_with_a_wrong_password_fails(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'supersecret10',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_user_can_update_and_delete_their_account(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'supersecret10',
        ]);
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/me', [
            'name' => 'Ada L.',
            'password' => 'evensecreter10',
            'password_confirmation' => 'evensecreter10',
            'current_password' => 'supersecret10',
        ])->assertOk()->assertJsonPath('user.name', 'Ada L.');

        $this->withToken($token)->deleteJson('/api/v1/me')->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_auth_endpoints_are_rate_limited(): void
    {
        $payload = ['email' => 'nobody@example.com', 'password' => 'whatever1234'];

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
    }
}
