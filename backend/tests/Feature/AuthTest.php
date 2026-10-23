<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_real_jwt_and_profile(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $response = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret-password']);
        $response->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('token_type', 'bearer');
        $token = $response->json('access_token');
        $this->assertCount(3, explode('.', $token));
        auth('api')->forgetUser();
        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');
    }

    public function test_invalid_credentials_and_invalid_token_are_rejected(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'invalid@example.com', 'password' => 'wrong'])->assertUnauthorized();
        $this->withToken('invalid-token')->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_logout_invalidates_jwt(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);
        auth('api')->forgetUser();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        auth('api')->forgetUser();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'invalid@example.com', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/login', ['email' => 'invalid@example.com', 'password' => 'wrong'])->assertStatus(429);
    }
}
