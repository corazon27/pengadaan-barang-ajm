<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentStatus: string
{
    case DETECTED = 'DETECTED';
    case TRIAGED = 'TRIAGED';
    case CLASSIFIED = 'CLASSIFIED';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    case CONFIRMED = 'CONFIRMED';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';
}
