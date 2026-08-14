<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->buyerB2b()->create([
            'email' => 'buyer@corporation.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'buyer@corporation.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'email',
                        'full_name',
                        'role',
                        'company_name',
                        'npwp_number',
                        'address',
                        'phone_number',
                    ],
                    'token',
                    'token_type',
                ],
                'errors',
            ])
            ->assertJsonPath('data.user.email', 'buyer@corporation.com')
            ->assertJsonPath('data.user.role', UserRole::BUYER_B2B->value)
            ->assertJsonPath('data.token_type', 'Bearer');

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'auth-token',
            'tokenable_id' => $user->id,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'auth-token',
            'abilities' => json_encode(['role:'.UserRole::BUYER_B2B->value]),
        ]);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@corporation.com',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Kredensial tidak valid.')
            ->assertJsonPath('data', null);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->superadmin()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', UserRole::SUPERADMIN->value);
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = User::factory()->buyerB2b()->create();

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'full_name' => 'Budi Santoso',
            'company_name' => 'PT Baru Nusantara',
            'npwp_number' => '12.345.678.9-012.345',
            'address' => 'Jl. Merdeka No. 10, Jakarta',
            'phone_number' => '081234567890',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.full_name', 'Budi Santoso')
            ->assertJsonPath('data.company_name', 'PT Baru Nusantara');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'full_name' => 'Budi Santoso',
            'company_name' => 'PT Baru Nusantara',
            'email' => $user->email,
            'role' => UserRole::BUYER_B2B->value,
        ]);
    }

    public function test_profile_update_ignores_role_and_email(): void
    {
        $user = User::factory()->buyerB2b()->create();

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'full_name' => 'Budi Santoso',
            'company_name' => 'PT Baru Nusantara',
            'address' => 'Jl. Merdeka No. 10, Jakarta',
            'phone_number' => '081234567890',
            'email' => 'hacker@example.com',
            'role' => UserRole::SUPERADMIN->value,
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => UserRole::BUYER_B2B->value,
        ]);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@corporation.com',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'buyer@corporation.com',
            'password' => 'password123',
        ]);

        $token = $login->json('data.token');

        auth()->guard('web')->logout();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        auth()->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Tidak terautentikasi.');
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $email = 'throttle@corporation.com';
        User::factory()->create(['email' => $email]);

        $payload = [
            'email' => $email,
            'password' => 'wrong-password',
        ];

        // First five attempts are allowed (per-minute limit = 5).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        }

        // The sixth attempt is throttled.
        $this->postJson('/api/v1/auth/login', $payload)
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors.throttle.0', 'Terlalu banyak permintaan. Silakan coba lagi setelah batas waktu.');
    }

    public function test_login_throttle_is_per_email_address(): void
    {
        $throttledEmail = 'throttled@corporation.com';
        $otherEmail = 'other@corporation.com';
        User::factory()->create(['email' => $throttledEmail]);
        User::factory()->create(['email' => $otherEmail]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $throttledEmail,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $throttledEmail,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        // A different account is unaffected by the throttled one.
        $this->postJson('/api/v1/auth/login', [
            'email' => $otherEmail,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_valid_login_succeeds_after_throttle_window_reset(): void
    {
        $email = 'reset@corporation.com';
        User::factory()->create(['email' => $email]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        // Simulate the rate-limit window elapsing, then valid credentials work.
        RateLimiter::clear(md5('login'.$email.'|127.0.0.1'));

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.user.email', $email)
            ->assertJsonPath('data.token_type', 'Bearer');
    }
}
