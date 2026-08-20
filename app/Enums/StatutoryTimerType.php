<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutoryTimerType: string
{
    case CONSENT_WITHDRAWAL = 'CONSENT_WITHDRAWAL';
    case RESTRICTION_SUSPENSION = 'RESTRICTION_SUSPENSION';
    case BREACH_NOTIFICATION = 'BREACH_NOTIFICATION';
}
