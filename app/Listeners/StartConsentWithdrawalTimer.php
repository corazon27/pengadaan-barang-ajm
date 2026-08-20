<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\StatutoryTimerStatus;
use App\Events\ConsentWithdrawn;
use App\Models\ConsentRecord;
use App\Models\StatutoryTimer;
use App\Services\StatutoryTimerService;
use Illuminate\Contracts\Queue\ShouldQueue;

class StartConsentWithdrawalTimer implements ShouldQueue
{
    public function __construct(
        private readonly StatutoryTimerService $timerService,
    ) {}

    public function handle(ConsentWithdrawn $event): void
    {
        $exists = StatutoryTimer::where('ref_type', ConsentRecord::class)
            ->where('ref_id', $event->consent->id)
            ->where('status', StatutoryTimerStatus::RUNNING)
            ->exists();

        if ($exists) {
            return;
        }

        $deadline = $this->timerService->startForConsentWithdrawal($event->consent);

        $event->consent->update([
            'withdrawal_deadline_at' => $deadline,
        ]);
    }
}
