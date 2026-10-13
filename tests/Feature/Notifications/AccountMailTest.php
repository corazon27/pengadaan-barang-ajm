<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use App\Notifications\NewAccountApplicationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the account lifecycle mail templates. These render the
 * MailMessage directly (rather than via Notification::fake) so a template that
 * throws while building the message — for example concatenating a backed enum —
 * fails the suite instead of silently dropping the notification.
 */
class AccountMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_application_mail_renders_for_a_b2g_requesting_applicant(): void
    {
        $admin = User::factory()->superadmin()->create();
        $applicant = User::factory()->pending()->create([
            'requested_role' => UserRole::BUYER_B2G,
        ]);

        $mail = (new NewAccountApplicationNotification($applicant))->toMail($admin);

        $this->assertSame('Pengajuan Akun Baru Menunggu Persetujuan', $mail->subject);
        $this->assertSame(route('admin.accounts.index'), $mail->actionUrl);
        $this->assertStringContainsString(
            UserRole::BUYER_B2G->value,
            implode("\n", $mail->introLines),
        );
    }

    public function test_status_changed_mail_for_activation_targets_the_login_route(): void
    {
        $user = User::factory()->create();

        $mail = (new AccountStatusChangedNotification(UserStatus::ACTIVE, UserRole::BUYER_B2B->value))
            ->toMail($user);

        $this->assertSame(route('login'), $mail->actionUrl);
    }

    public function test_status_changed_mail_includes_the_rejection_reason(): void
    {
        $user = User::factory()->rejected()->create();

        $mail = (new AccountStatusChangedNotification(UserStatus::REJECTED, null, 'Dokumen tidak lengkap.'))
            ->toMail($user);

        $this->assertStringContainsString('Dokumen tidak lengkap.', implode("\n", $mail->introLines));
    }
}
