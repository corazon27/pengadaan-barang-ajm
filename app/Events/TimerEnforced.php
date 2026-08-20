<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\StatutoryTimer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimerEnforced
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly StatutoryTimer $timer,
    ) {}
}
