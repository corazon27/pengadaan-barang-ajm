<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\BreachNotificationType;
use App\Enums\StatutoryTimerStatus;
use App\Events\BreachNotificationSent;
use App\Models\IncidentRegister;
use App\Models\StatutoryTimer;
use App\Services\StatutoryTimerService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SignalBreachNotificationSent implements ShouldQueue
{
    public function __construct(
        private readonly StatutoryTimerService $timerService,
    ) {}

    /**
     * When an AUTHORITY notification is sent, attempt to mark the associated
     * breach-notification timer as MET.
     *
     * Only AUTHORITY notifications satisfy PDP-3X24-003.
     * DATA_SUBJECT and SUPERVISOR notifications do NOT satisfy the timer.
     * Idempotent: markMet() is a no-op if timer is not RUNNING.
     */
    public function handle(BreachNotificationSent $event): void
    {
        if ($event->notification->notification_type !== BreachNotificationType::AUTHORITY) {
            return;
        }

        $timer = StatutoryTimer::where('ref_type', IncidentRegister::class)
            ->where('ref_id', $event->incident->id)
            ->where('status', StatutoryTimerStatus::RUNNING)
            ->first();

        if ($timer !== null) {
            $this->timerService->markMet($timer);
        }
    }
}
