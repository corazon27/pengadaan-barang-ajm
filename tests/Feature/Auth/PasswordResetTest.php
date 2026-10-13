<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_updates_password_and_preserves_lifecycle_state(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'email_verified_at' => now(),
        ]);
        $user->createToken('existing');

        $token = Password::broker()->createToken($user);

        $this->post('/atur-ulang-kata-sandi', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('login'));

        $fresh = $user->fresh();

        $this->assertTrue(Hash::check('brand-new-password', $fresh->password));
        // Lifecycle, role, and verification state must be untouched.
        $this->assertSame(UserStatus::ACTIVE, $fresh->status);
        $this->assertSame(UserRole::BUYER_B2B, $fresh->role);
        $this->assertNotNull($fresh->email_verified_at);
        // Existing API credentials are revoked.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->from('/atur-ulang-kata-sandi')
            ->post('/atur-ulang-kata-sandi', [
                'token' => 'invalid-token',
                'email' => 'reset@example.com',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_forgot_password_response_is_generic(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        $this->post('/lupa-kata-sandi', ['email' => 'known@example.com'])
            ->assertSessionHas('status');

        $this->post('/lupa-kata-sandi', ['email' => 'unknown@example.com'])
            ->assertSessionHas('status');
    }
}
