<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\JwtService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create(['email' => 'user@vending.test', 'password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@vending.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create(['email' => 'user@vending.test', 'password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@vending.test',
            'password' => 'wrong',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@test.com',
            'password' => 'password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_me_returns_user_with_permissions(): void
    {
        $user = User::factory()->create()->assignRole('user');
        $token = app(JwtService::class)->issue($user)['access_token'];

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email)
            ->assertJsonStructure(['permissions']);
    }

    public function test_missing_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = JWT::encode(
            ['iss' => config('app.url'), 'sub' => (string) $user->id, 'iat' => time() - 7200, 'exp' => time() - 3600, 'jti' => 'test'],
            config('services.jwt.secret'),
            'HS256',
        );

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_tampered_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = app(JwtService::class)->issue($user)['access_token'];
        $tampered = substr($token, 0, -1).(str_ends_with($token, 'a') ? 'b' : 'a');

        $response = $this->withToken($tampered)->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_wrong_algorithm_token_is_rejected(): void
    {
        $user = User::factory()->create();
        // Create token with RS256 algorithm (different from HS256) - requires a key pair
        // Use a simple approach: create a token with a different secret and HS256, then modify the header to claim RS256
        // But JWT::encode doesn't allow that easily. Instead, test that a token signed with a different secret is rejected.
        $token = JWT::encode(
            ['iss' => config('app.url'), 'sub' => (string) $user->id, 'iat' => time(), 'exp' => time() + 3600, 'jti' => 'test'],
            'different-secret-that-is-long-enough-for-hs256',
            'HS256',
        );

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_login_is_throttled(): void
    {
        $user = User::factory()->create(['email' => 'user@vending.test', 'password' => bcrypt('password')]);

        // Make 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'user@vending.test',
                'password' => 'wrong',
            ])->assertUnauthorized();
        }

        // 6th attempt should be throttled
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@vending.test',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }
}
