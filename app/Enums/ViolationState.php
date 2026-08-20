<?php

declare(strict_types=1);

namespace App\Enums;

enum ViolationState: string
{
    case AUDIT = 'AUDIT';
    case REPORTED = 'REPORTED';
    case ESCALATED = 'ESCALATED';
}
