<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'baru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'full_name' => 'Pengguna Baru',
            'company_name' => 'PT Baru',
            'address' => 'Jl. Contoh No. 1',
            'phone_number' => '081234567890',
        ], $overrides);
    }

    public function test_registration_creates_a_pending_account_and_audits_it(): void
    {
        Notification::fake();

        $this->post('/daftar', $this->payload())
            ->assertRedirect(route('login'));

        $user = User::where('email', 'baru@example.com')->firstOrFail();

        $this->assertSame(UserStatus::PENDING, $user->status);
        $this->assertSame(UserRole::BUYER_B2B, $user->role);
        $this->assertNull($user->email_verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ACCOUNT_REGISTERED->value,
            'entity_id' => $user->getKey(),
        ]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_requested_role_is_recorded_as_intent_only(): void
    {
        Notification::fake();

        $this->post('/daftar', $this->payload([
            'requested_role' => UserRole::BUYER_B2G->value,
        ]))->assertRedirect(route('login'));

        $user = User::where('email', 'baru@example.com')->firstOrFail();

        $this->assertSame(UserRole::BUYER_B2B, $user->role, 'Requested role must never be granted at registration.');
        $this->assertSame(UserRole::BUYER_B2G, $user->requested_role);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'baru@example.com']);

        $this->post('/daftar', $this->payload())
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_privileged_requested_role_is_rejected(): void
    {
        Notification::fake();

        $this->post('/daftar', $this->payload([
            'requested_role' => UserRole::SUPERADMIN->value,
        ]))->assertSessionHasErrors('requested_role');
    }

    public function test_password_confirmation_must_match(): void
    {
        Notification::fake();

        $this->post('/daftar', $this->payload([
            'password_confirmation' => 'different-password',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }
}
