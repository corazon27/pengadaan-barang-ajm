<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\AuditAction;
use App\Enums\StatutoryTimerEnforcement;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Enums\ViolationState;
use App\Models\ConsentRecord;
use App\Models\DataSubjectRequest;
use App\Models\IncidentRegister;
use App\Models\StatutoryTimer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforceStatutoryTimersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_enforces_expired_consent_timer_to_violated(): void
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

        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->artisan('timers:enforce-statutory')
            ->expectsOutputToContain('1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL->value,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'status' => StatutoryTimerStatus::VIOLATED->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TIMER_VIOLATED->value,
            'entity_type' => 'StatutoryTimer',
        ]);
    }

    public function test_enforces_expired_breach_timer_to_escalated(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => IncidentRegister::class,
            'ref_id' => 'test-incident-001',
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->artisan('timers:enforce-statutory')
            ->expectsOutputToContain('1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION->value,
            'ref_type' => IncidentRegister::class,
            'ref_id' => 'test-incident-001',
            'status' => StatutoryTimerStatus::ESCALATED->value,
            'violation_state' => ViolationState::ESCALATED->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TIMER_ESCALATED->value,
            'entity_type' => 'StatutoryTimer',
        ]);
    }

    public function test_enforces_expired_restriction_timer_to_violated(): void
    {
        $user = User::factory()->create();
        $dsr = DataSubjectRequest::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-007',
            'channel' => 'EMAIL',
            'status' => 'RECEIVED',
            'applicability_status' => 'APPLICABILITY_UNKNOWN',
        ]);

        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::RESTRICTION_SUSPENSION,
            'enforcement' => StatutoryTimerEnforcement::SUSPEND_RESTRICT_PROCESSING,
            'ref_type' => DataSubjectRequest::class,
            'ref_id' => $dsr->id,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->artisan('timers:enforce-statutory')
            ->expectsOutputToContain('1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::RESTRICTION_SUSPENSION->value,
            'ref_type' => DataSubjectRequest::class,
            'ref_id' => $dsr->id,
            'status' => StatutoryTimerStatus::VIOLATED->value,
        ]);
    }

    public function test_no_expired_timers_succeeds_cleanly(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => 'non-expired-id',
            'started_at' => now()->subHours(10),
            'deadline_at' => now()->addHours(62),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->artisan('timers:enforce-statutory')
            ->expectsOutputToContain('0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_id' => 'non-expired-id',
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::TIMER_VIOLATED->value,
            'entity_type' => 'StatutoryTimer',
        ]);
    }

    public function test_empty_database_succeeds(): void
    {
        $this->artisan('timers:enforce-statutory')
            ->expectsOutputToContain('0')
            ->assertExitCode(0);
    }

    public function test_skips_already_met_timers(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => 'met-timer-id',
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::MET,
        ]);

        $this->artisan('timers:enforce-statutory')
            ->expectsOutputToContain('0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_id' => 'met-timer-id',
            'status' => StatutoryTimerStatus::MET->value,
        ]);
    }

    public function test_skips_already_cancelled_timers(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => 'cancelled-timer-id',
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::CANCELLED,
        ]);

        $this->artisan('timers:enforce-statutory')
            ->expectsOutputToContain('0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_id' => 'cancelled-timer-id',
            'status' => StatutoryTimerStatus::CANCELLED->value,
        ]);
    }

    public function test_enforces_multiple_expired_timers_in_single_run(): void
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

        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => IncidentRegister::class,
            'ref_id' => 'multi-test-incident',
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->artisan('timers:enforce-statutory')
            ->expectsOutputToContain('2')
            ->assertExitCode(0);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'status' => StatutoryTimerStatus::VIOLATED->value,
        ]);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_type' => IncidentRegister::class,
            'ref_id' => 'multi-test-incident',
            'status' => StatutoryTimerStatus::ESCALATED->value,
        ]);
    }

    public function test_creates_no_duplicate_timers_on_rerun(): void
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

        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->artisan('timers:enforce-statutory')->assertExitCode(0);
        $this->artisan('timers:enforce-statutory')
            ->expectsOutputToContain('0')
            ->assertExitCode(0);

        $count = StatutoryTimer::where('ref_type', ConsentRecord::class)
            ->where('ref_id', $record->id)
            ->count();
        $this->assertSame(1, $count);
    }
}
