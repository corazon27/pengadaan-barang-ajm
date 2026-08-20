<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StatutoryTimerService;
use Illuminate\Console\Command;

class EnforceStatutoryTimers extends Command
{
    protected $signature = 'timers:enforce-statutory';

    protected $description = 'Enforce expired statutory timers (3×24h PDP-3X24-001/002/003)';

    public function handle(StatutoryTimerService $timerService): int
    {
        $enforced = $timerService->enforceExpiredTimers();

        $this->components->info('Enforced '.count($enforced).' statutory timer(s).');

        return Command::SUCCESS;
    }
}
