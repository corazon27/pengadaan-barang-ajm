<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AccountLifecycleService;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class AccountLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_rolls_back_when_the_mandatory_audit_write_fails(): void
    {
        $this->mock(AuditLogger::class, function ($mock): void {
            $mock->shouldReceive('strictLog')->andThrow(new RuntimeException('audit store down'));
        });

        $actor = User::factory()->superadmin()->create();
        $target = User::factory()->pending()->create(['email_verified_at' => now()]);

        try {
            app(AccountLifecycleService::class)->approve(
                $actor,
                $target,
                UserRole::BUYER_B2B,
                'Dokumen lengkap.',
            );
            $this->fail('Expected the audit failure to abort the approval.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(
            UserStatus::PENDING,
            $target->fresh()->status,
            'A failed mandatory audit write must roll the status change back.',
        );
    }

    public function test_approval_requires_a_pending_account(): void
    {
        $actor = User::factory()->superadmin()->create();
        $target = User::factory()->create(['email_verified_at' => now()]);

        $this->expectException(ValidationException::class);

        app(AccountLifecycleService::class)->approve($actor, $target, UserRole::BUYER_B2B, 'Coba.');
    }

    public function test_email_verification_audits_once_and_is_idempotent(): void
    {
        $service = app(AccountLifecycleService::class);
        $user = User::factory()->pending()->create(['email_verified_at' => null]);

        $this->assertTrue($service->markEmailVerified($user));
        $this->assertFalse($service->markEmailVerified($user->fresh()));

        $this->assertSame(1, $user->fresh()->auditLogs()->where('action', AuditAction::EMAIL_VERIFIED->value)->count());

        // Verification must never mutate status.
        $this->assertSame(UserStatus::PENDING, $user->fresh()->status);
    }
}
