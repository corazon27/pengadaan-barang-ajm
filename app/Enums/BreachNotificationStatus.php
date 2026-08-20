<?php

declare(strict_types=1);

namespace App\Enums;

enum BreachNotificationStatus: string
{
    case PENDING = 'PENDING';
    case PREPARING = 'PREPARING';
    case READY = 'READY';
    case SENT = 'SENT';
    case CONFIRMED = 'CONFIRMED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
