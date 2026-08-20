<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Pdp;

use App\Enums\AuditAction;
use App\Enums\ConsentStatus;
use App\Enums\StatutoryTimerEnforcement;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Enums\ViolationState;
use App\Events\BreachQualifiedEvent;
use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\DataSubjectRequest;
use App\Models\StatutoryTimer;
use App\Models\User;
use App\Services\StatutoryTimerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatutoryTimerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private StatutoryTimerService $timerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timerService = app(StatutoryTimerService::class);
    }

    private function createWithdrawnConsent(): ConsentRecord
    {
        $user = User::factory()->create();
        $record = ConsentRecord::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'purpose' => 'marketing',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2026',
            'source_channel' => 'WEB',
            'granted_at' => now(),
        ]);
        $record->update([
            'consent_status' => ConsentStatus::WITHDRAWN,
            'withdrawn_at' => now(),
        ]);

        return $record->fresh();
    }

    private function createDsr(string $rightCode = 'PDP-RIGHT-007', string $status = 'RECEIVED'): DataSubjectRequest
    {
        $user = User::factory()->create();

        return DataSubjectRequest::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'right_code' => $rightCode,
            'channel' => 'EMAIL',
            'status' => $status,
            'applicability_status' => 'APPLICABILITY_UNKNOWN',
        ]);
    }

    public function test_create_consent_withdrawal_timer(): void
    {
        $record = $this->createWithdrawnConsent();

        $deadline = $this->timerService->startForConsentWithdrawal($record);

        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL->value,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
        $this->assertTrue($deadline->eq(now()->addHours(72)->startOfMinute()) || $deadline->gt(now()->addHours(71)));
    }

    public function test_create_restriction_suspension_timer(): void
    {
        $dsr = $this->createDsr();

        $deadline = $this->timerService->startForRestrictionSuspension($dsr);

        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::RESTRICTION_SUSPENSION->value,
            'ref_type' => DataSubjectRequest::class,
            'ref_id' => $dsr->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
        $this->assertTrue($deadline->gt(now()->addHours(71)));
    }

    public function test_create_breach_notification_timer_uses_source_reference(): void
    {
        $event = new BreachQualifiedEvent(
            sourceType: 'HumanReviewCase',
            sourceId: 'test-hrc-id-001',
            detectedAt: now(),
            ruleId: 'PDP-BREACH-001',
        );

        $deadline = $this->timerService->startForBreachNotification($event);

        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION->value,
            'ref_type' => 'HumanReviewCase',
            'ref_id' => 'test-hrc-id-001',
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
        $this->assertTrue($deadline->gt(now()->addHours(71)));
    }

    public function test_cancel_running_timer(): void
    {
        $record = $this->createWithdrawnConsent();
        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now(),
            'deadline_at' => now()->addHours(72),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $result = $this->timerService->cancel($timer);

        $this->assertSame(StatutoryTimerStatus::CANCELLED, $result->status);
        $this->assertDatabaseHas('statutory_timers', [
            'id' => $timer->id,
            'status' => StatutoryTimerStatus::CANCELLED->value,
        ]);
    }

    public function test_cannot_cancel_non_running_timer(): void
    {
        $record = $this->createWithdrawnConsent();
        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now(),
            'deadline_at' => now()->addHours(72),
            'status' => StatutoryTimerStatus::MET,
        ]);

        $result = $this->timerService->cancel($timer);

        $this->assertSame(StatutoryTimerStatus::MET, $result->status);
    }

    public function test_mark_met_transitions_running_to_met(): void
    {
        $record = $this->createWithdrawnConsent();
        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now(),
            'deadline_at' => now()->addHours(72),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $result = $this->timerService->markMet($timer);

        $this->assertSame(StatutoryTimerStatus::MET, $result->status);
        $this->assertDatabaseHas('statutory_timers', [
            'id' => $timer->id,
            'status' => StatutoryTimerStatus::MET->value,
        ]);
    }

    public function test_mark_met_is_idempotent_on_met_timer(): void
    {
        $record = $this->createWithdrawnConsent();
        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now(),
            'deadline_at' => now()->addHours(72),
            'status' => StatutoryTimerStatus::MET,
        ]);

        $result = $this->timerService->markMet($timer);

        $this->assertSame(StatutoryTimerStatus::MET, $result->status);
    }

    public function test_enforce_expired_consent_timer_to_violated(): void
    {
        $record = $this->createWithdrawnConsent();
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $enforced = $this->timerService->enforceExpiredTimers();

        $this->assertCount(1, $enforced);
        $this->assertSame(StatutoryTimerStatus::VIOLATED, $enforced[0]->status);
    }

    public function test_enforce_expired_restriction_timer_to_violated(): void
    {
        $dsr = $this->createDsr();
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::RESTRICTION_SUSPENSION,
            'enforcement' => StatutoryTimerEnforcement::SUSPEND_RESTRICT_PROCESSING,
            'ref_type' => DataSubjectRequest::class,
            'ref_id' => $dsr->id,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $enforced = $this->timerService->enforceExpiredTimers();

        $this->assertCount(1, $enforced);
        $this->assertSame(StatutoryTimerStatus::VIOLATED, $enforced[0]->status);
    }

    public function test_enforce_expired_breach_timer_to_escalated(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => 'HumanReviewCase',
            'ref_id' => 'hrc-test-001',
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $enforced = $this->timerService->enforceExpiredTimers();

        $this->assertCount(1, $enforced);
        $this->assertSame(StatutoryTimerStatus::ESCALATED, $enforced[0]->status);
        $this->assertSame(ViolationState::ESCALATED, $enforced[0]->violation_state);
    }

    public function test_enforce_skips_met_timers(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::MET,
        ]);

        $enforced = $this->timerService->enforceExpiredTimers();

        $this->assertCount(0, $enforced);
    }

    public function test_enforce_skips_cancelled_timers(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::CANCELLED,
        ]);

        $enforced = $this->timerService->enforceExpiredTimers();

        $this->assertCount(0, $enforced);
    }

    public function test_timer_created_audit_log(): void
    {
        $record = $this->createWithdrawnConsent();
        $this->timerService->startForConsentWithdrawal($record);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TIMER_CREATED->value,
            'entity_type' => 'StatutoryTimer',
        ]);
    }

    public function test_withdrawn_consent_remains_withdrawn_after_timer_met(): void
    {
        $record = $this->createWithdrawnConsent();
        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now(),
            'deadline_at' => now()->addHours(72),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->timerService->markMet($timer);

        $record->refresh();
        $this->assertSame(ConsentStatus::WITHDRAWN, $record->consent_status);
    }

    public function test_duplicate_consent_withdrawal_creates_exactly_one_timer(): void
    {
        $record = $this->createWithdrawnConsent();

        $this->timerService->startForConsentWithdrawal($record);
        $this->timerService->startForConsentWithdrawal($record);

        $count = StatutoryTimer::where('ref_type', ConsentRecord::class)
            ->where('ref_id', $record->id)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_dsr_submission_does_not_create_restriction_timer(): void
    {
        $dsr = $this->createDsr('PDP-RIGHT-007', 'RECEIVED');

        $count = StatutoryTimer::where('ref_type', DataSubjectRequest::class)
            ->where('ref_id', $dsr->id)
            ->count();
        $this->assertSame(0, $count);
    }

    public function test_consent_withdrawal_violated_logs_timer_violated(): void
    {
        $record = $this->createWithdrawnConsent();
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $enforced = $this->timerService->enforceExpiredTimers();

        $this->assertCount(1, $enforced);
        $this->assertSame(StatutoryTimerStatus::VIOLATED, $enforced[0]->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TIMER_VIOLATED->value,
            'entity_type' => 'StatutoryTimer',
            'entity_id' => $enforced[0]->id,
        ]);

        $log = AuditLog::where('entity_type', 'StatutoryTimer')
            ->where('entity_id', $enforced[0]->id)
            ->where('action', AuditAction::TIMER_VIOLATED->value)
            ->first();
        $this->assertSame('RUNNING', $log->previous_state['status']);
        $this->assertSame('VIOLATED', $log->new_state['status']);
    }

    public function test_restriction_suspension_violated_logs_timer_violated(): void
    {
        $dsr = $this->createDsr();
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::RESTRICTION_SUSPENSION,
            'enforcement' => StatutoryTimerEnforcement::SUSPEND_RESTRICT_PROCESSING,
            'ref_type' => DataSubjectRequest::class,
            'ref_id' => $dsr->id,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $enforced = $this->timerService->enforceExpiredTimers();

        $this->assertCount(1, $enforced);
        $this->assertSame(StatutoryTimerStatus::VIOLATED, $enforced[0]->status);

        $log = AuditLog::where('entity_type', 'StatutoryTimer')
            ->where('entity_id', $enforced[0]->id)
            ->where('action', AuditAction::TIMER_VIOLATED->value)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('RUNNING', $log->previous_state['status']);
        $this->assertSame('VIOLATED', $log->new_state['status']);
    }

    public function test_mark_met_logs_timer_met_audit_with_states(): void
    {
        $record = $this->createWithdrawnConsent();
        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now(),
            'deadline_at' => now()->addHours(72),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $result = $this->timerService->markMet($timer);

        $this->assertSame(StatutoryTimerStatus::MET, $result->status);

        $log = AuditLog::where('entity_type', 'StatutoryTimer')
            ->where('entity_id', $timer->id)
            ->where('action', AuditAction::TIMER_MET->value)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('RUNNING', $log->previous_state['status']);
        $this->assertSame('MET', $log->new_state['status']);
    }

    public function test_breach_expired_logs_timer_escalated_with_states(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => 'HumanReviewCase',
            'ref_id' => 'hrc-audit-test-001',
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $enforced = $this->timerService->enforceExpiredTimers();

        $this->assertCount(1, $enforced);
        $this->assertSame(StatutoryTimerStatus::ESCALATED, $enforced[0]->status);

        $log = AuditLog::where('entity_type', 'StatutoryTimer')
            ->where('entity_id', $enforced[0]->id)
            ->where('action', AuditAction::TIMER_ESCALATED->value)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('RUNNING', $log->previous_state['status']);
        $this->assertSame('ESCALATED', $log->new_state['status']);
    }

    public function test_cancel_logs_timer_cancelled_with_states(): void
    {
        $record = $this->createWithdrawnConsent();
        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now(),
            'deadline_at' => now()->addHours(72),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $result = $this->timerService->cancel($timer);

        $this->assertSame(StatutoryTimerStatus::CANCELLED, $result->status);

        $log = AuditLog::where('entity_type', 'StatutoryTimer')
            ->where('entity_id', $timer->id)
            ->where('action', AuditAction::TIMER_CANCELLED->value)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('RUNNING', $log->previous_state['status']);
        $this->assertSame('CANCELLED', $log->new_state['status']);
    }
}
