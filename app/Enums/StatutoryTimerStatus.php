<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutoryTimerStatus: string
{
    case RUNNING = 'RUNNING';
    case MET = 'MET';
    case VIOLATED = 'VIOLATED';
    case ESCALATED = 'ESCALATED';
    case CANCELLED = 'CANCELLED';
}
