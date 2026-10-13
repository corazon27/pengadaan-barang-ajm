<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function signedShowUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.show', now()->addHour(), [
            'user' => $user->getKey(),
            'hash' => sha1($user->email),
        ]);
    }

    private function signedVerifyUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'user' => $user->getKey(),
            'hash' => sha1($user->email),
        ]);
    }

    public function test_get_renders_confirmation_without_any_state_change(): void
    {
        $user = User::factory()->pending()->create();

        $this->get($this->signedShowUrl($user))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/VerifyConfirm'));

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(UserStatus::PENDING, $user->fresh()->status);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_post_verifies_email_and_keeps_account_pending(): void
    {
        $user = User::factory()->pending()->create();

        $this->post($this->signedVerifyUrl($user))
            ->assertRedirect(route('login'));

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertSame(UserStatus::PENDING, $fresh->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::EMAIL_VERIFIED->value,
            'entity_id' => $user->getKey(),
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_verification_is_idempotent(): void
    {
        $user = User::factory()->pending()->create(['email_verified_at' => now()]);

        $this->post($this->signedVerifyUrl($user))->assertRedirect(route('login'));

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::EMAIL_VERIFIED->value,
            'entity_id' => $user->getKey(),
        ]);
    }

    public function test_unsigned_link_is_forbidden(): void
    {
        $user = User::factory()->pending()->create();

        $this->get(route('verification.show', [
            'user' => $user->getKey(),
            'hash' => sha1($user->email),
        ]))->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_signed_link_with_wrong_email_hash_is_forbidden(): void
    {
        $user = User::factory()->pending()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'user' => $user->getKey(),
            'hash' => sha1('someone-else@example.com'),
        ]);

        $this->post($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_response_is_generic_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        $user = User::factory()->pending()->create(['email' => 'known@example.com']);

        $this->post('/verifikasi-email/kirim-ulang', ['email' => 'known@example.com'])
            ->assertSessionHas('status');
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        Notification::fake();
        $this->post('/verifikasi-email/kirim-ulang', ['email' => 'unknown@example.com'])
            ->assertSessionHas('status');
        Notification::assertNothingSent();
    }
}
