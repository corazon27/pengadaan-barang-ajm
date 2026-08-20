<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\StatutoryTimerStatus;
use App\Events\RestrictionAccepted;
use App\Models\DataSubjectRequest;
use App\Models\StatutoryTimer;
use App\Services\StatutoryTimerService;
use Illuminate\Contracts\Queue\ShouldQueue;

class StartRestrictionTimer implements ShouldQueue
{
    public function __construct(
        private readonly StatutoryTimerService $timerService,
    ) {}

    public function handle(RestrictionAccepted $event): void
    {
        $exists = StatutoryTimer::where('ref_type', DataSubjectRequest::class)
            ->where('ref_id', $event->dsr->id)
            ->where('status', StatutoryTimerStatus::RUNNING)
            ->exists();

        if ($exists) {
            return;
        }

        $this->timerService->startForRestrictionSuspension($event->dsr);
    }
}
