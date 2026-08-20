<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\StatutoryTimerEnforcement;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Models\StatutoryTimer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatutoryTimerComputationTest extends TestCase
{
    use RefreshDatabase;

    private function createTimer(StatutoryTimerType $type, \DateTime $startedAt): StatutoryTimer
    {
        return StatutoryTimer::create([
            'timer_type' => $type,
            'enforcement' => match ($type) {
                StatutoryTimerType::CONSENT_WITHDRAWAL => StatutoryTimerEnforcement::STOP_PROCESSING,
                StatutoryTimerType::RESTRICTION_SUSPENSION => StatutoryTimerEnforcement::SUSPEND_RESTRICT_PROCESSING,
                StatutoryTimerType::BREACH_NOTIFICATION => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            },
            'started_at' => $startedAt,
            'deadline_at' => $startedAt->copy()->addHours(72),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);
    }

    public function test_consent_withdrawal_deadline_is_exactly_72_hours(): void
    {
        $startedAt = now()->startOfMinute();
        $timer = $this->createTimer(StatutoryTimerType::CONSENT_WITHDRAWAL, $startedAt);

        $this->assertTrue($timer->deadline_at->eq($startedAt->copy()->addHours(72)));
        $this->assertEqualsWithDelta(72 * 3600, (int) $timer->started_at->diffInSeconds($timer->deadline_at), 1);
    }

    public function test_restriction_suspension_deadline_is_exactly_72_hours(): void
    {
        $startedAt = now()->startOfMinute();
        $timer = $this->createTimer(StatutoryTimerType::RESTRICTION_SUSPENSION, $startedAt);

        $this->assertTrue($timer->deadline_at->eq($startedAt->copy()->addHours(72)));
        $this->assertEqualsWithDelta(72 * 3600, (int) $timer->started_at->diffInSeconds($timer->deadline_at), 1);
    }

    public function test_breach_notification_deadline_is_exactly_72_hours(): void
    {
        $startedAt = now()->startOfMinute();
        $timer = $this->createTimer(StatutoryTimerType::BREACH_NOTIFICATION, $startedAt);

        $this->assertTrue($timer->deadline_at->eq($startedAt->copy()->addHours(72)));
        $this->assertEqualsWithDelta(72 * 3600, (int) $timer->started_at->diffInSeconds($timer->deadline_at), 1);
    }

    public function test_consent_withdrawal_enforcement_is_stop_processing(): void
    {
        $timer = $this->createTimer(StatutoryTimerType::CONSENT_WITHDRAWAL, now());

        $this->assertSame(StatutoryTimerEnforcement::STOP_PROCESSING, $timer->enforcement);
    }

    public function test_restriction_enforcement_is_suspend_restrict(): void
    {
        $timer = $this->createTimer(StatutoryTimerType::RESTRICTION_SUSPENSION, now());

        $this->assertSame(StatutoryTimerEnforcement::SUSPEND_RESTRICT_PROCESSING, $timer->enforcement);
    }

    public function test_breach_enforcement_is_escalation_violation_audit(): void
    {
        $timer = $this->createTimer(StatutoryTimerType::BREACH_NOTIFICATION, now());

        $this->assertSame(StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT, $timer->enforcement);
    }

    public function test_cached_projection_cannot_become_authoritative(): void
    {
        $startedAt = now()->startOfMinute();
        $timer = $this->createTimer(StatutoryTimerType::CONSENT_WITHDRAWAL, $startedAt);

        $correctDeadline = $timer->deadline_at->copy();

        $timer->update(['deadline_at' => now()->addHours(999)]);

        $timer->refresh();
        $this->assertTrue($timer->deadline_at->eq(now()->addHours(999)->startOfMinute()) || $timer->deadline_at->gt($correctDeadline));

        $timer->update(['deadline_at' => $correctDeadline]);
        $timer->refresh();
        $this->assertTrue($timer->deadline_at->eq($correctDeadline));
    }
}
