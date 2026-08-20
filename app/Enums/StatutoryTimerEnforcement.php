<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutoryTimerEnforcement: string
{
    case STOP_PROCESSING = 'STOP_PROCESSING';
    case SUSPEND_RESTRICT_PROCESSING = 'SUSPEND_RESTRICT_PROCESSING';
    case ESCALATION_VIOLATION_AUDIT = 'ESCALATION_VIOLATION_AUDIT';
}
