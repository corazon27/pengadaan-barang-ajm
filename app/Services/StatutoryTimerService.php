<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\StatutoryTimerEnforcement;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Enums\ViolationState;
use App\Events\BreachQualifiedEvent;
use App\Events\TimerEnforced;
use App\Models\ConsentRecord;
use App\Models\DataSubjectRequest;
use App\Models\StatutoryTimer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatutoryTimerService
{
    private const DEADLINE_HOURS = 72;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Start a consent withdrawal timer (PDP-3X24-001).
     * Called within the same transaction as consent withdrawal.
     *
     * @return \DateTime The deadline_at value for cached projection
     */
    public function startForConsentWithdrawal(ConsentRecord $record): \DateTime
    {
        $existing = StatutoryTimer::where('ref_type', ConsentRecord::class)
            ->where('ref_id', $record->id)
            ->where('status', StatutoryTimerStatus::RUNNING)
            ->first();

        if ($existing !== null) {
            return $existing->deadline_at;
        }

        /** @var Carbon $startedAt */
        $startedAt = $record->withdrawn_at ?? now();
        $deadlineAt = (clone $startedAt)->addHours(self::DEADLINE_HOURS);

        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL,
            'enforcement' => StatutoryTimerEnforcement::STOP_PROCESSING,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'started_at' => $startedAt,
            'deadline_at' => $deadlineAt,
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->auditLogger->log(null, AuditAction::TIMER_CREATED, $timer);

        return $timer->deadline_at;
    }

    /**
     * Start a restriction/suspension timer (PDP-3X24-002).
     * Called when restriction is accepted/activated, NOT on DSR submission.
     *
     * @return \DateTime The deadline_at value
     */
    public function startForRestrictionSuspension(DataSubjectRequest $dsr): \DateTime
    {
        $existing = StatutoryTimer::where('ref_type', DataSubjectRequest::class)
            ->where('ref_id', $dsr->id)
            ->where('status', StatutoryTimerStatus::RUNNING)
            ->first();

        if ($existing !== null) {
            return $existing->deadline_at;
        }

        $deadlineAt = now()->addHours(self::DEADLINE_HOURS);

        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::RESTRICTION_SUSPENSION,
            'enforcement' => StatutoryTimerEnforcement::SUSPEND_RESTRICT_PROCESSING,
            'ref_type' => DataSubjectRequest::class,
            'ref_id' => $dsr->id,
            'started_at' => now(),
            'deadline_at' => $deadlineAt,
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->auditLogger->log(null, AuditAction::TIMER_CREATED, $timer);

        return $timer->deadline_at;
    }

    /**
     * Start a breach notification timer (PDP-3X24-003).
     * Triggered by a BreachQualifiedEvent from Phase 3B.4.
     * Uses sourceType/sourceId for stable reference, NOT event class name.
     *
     * @return \DateTime The deadline_at value
     */
    public function startForBreachNotification(BreachQualifiedEvent $event): \DateTime
    {
        $existing = StatutoryTimer::where('ref_type', $event->sourceType)
            ->where('ref_id', $event->sourceId)
            ->where('status', StatutoryTimerStatus::RUNNING)
            ->first();

        if ($existing !== null) {
            return $existing->deadline_at;
        }

        $deadlineAt = Carbon::instance($event->detectedAt)->addHours(self::DEADLINE_HOURS);

        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => $event->sourceType,
            'ref_id' => $event->sourceId,
            'started_at' => $event->detectedAt,
            'deadline_at' => $deadlineAt,
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $this->auditLogger->log(null, AuditAction::TIMER_CREATED, $timer);

        return $timer->deadline_at;
    }

    /**
     * Mark a timer as MET — substantive obligation completed on/before deadline.
     * Must only be called when the domain action is explicitly confirmed as completed.
     * Idempotent: no-op if timer is not RUNNING.
     */
    public function markMet(StatutoryTimer $timer): StatutoryTimer
    {
        if ($timer->status !== StatutoryTimerStatus::RUNNING) {
            return $timer;
        }

        $previousState = $this->auditLogger->snapshot($timer);

        $timer->update(['status' => StatutoryTimerStatus::MET]);

        $this->auditLogger->log(null, AuditAction::TIMER_MET, $timer, $previousState);

        return $timer->fresh();
    }

    /**
     * Cancel a running timer.
     * Idempotent: no-op if timer is not RUNNING.
     */
    public function cancel(StatutoryTimer $timer): StatutoryTimer
    {
        if ($timer->status !== StatutoryTimerStatus::RUNNING) {
            return $timer;
        }

        $previousState = $this->auditLogger->snapshot($timer);

        $timer->update(['status' => StatutoryTimerStatus::CANCELLED]);

        $this->auditLogger->log(null, AuditAction::TIMER_CANCELLED, $timer, $previousState);

        return $timer->fresh();
    }

    /**
     * Enforce all expired RUNNING timers.
     * BREACH_NOTIFICATION → ESCALATED; others → VIOLATED.
     */
    public function enforceExpiredTimers(): array
    {
        $enforced = [];

        DB::transaction(function () use (&$enforced) {
            $expired = StatutoryTimer::query()
                ->lockForUpdate()
                ->where('status', StatutoryTimerStatus::RUNNING)
                ->where('deadline_at', '<=', now())
                ->get();

            foreach ($expired as $timer) {
                $previousState = $this->auditLogger->snapshot($timer);

                if ($timer->timer_type === StatutoryTimerType::BREACH_NOTIFICATION) {
                    $timer->update([
                        'status' => StatutoryTimerStatus::ESCALATED,
                        'violation_state' => ViolationState::ESCALATED,
                    ]);
                    $this->auditLogger->log(null, AuditAction::TIMER_ESCALATED, $timer, $previousState);
                } else {
                    $timer->update(['status' => StatutoryTimerStatus::VIOLATED]);
                    $this->auditLogger->log(null, AuditAction::TIMER_VIOLATED, $timer, $previousState);
                }

                $enforced[] = $timer->fresh();

                event(new TimerEnforced($timer->fresh()));
            }
        });

        return $enforced;
    }
}
