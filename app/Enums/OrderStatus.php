<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PROCESSING = 'PROCESSING';
    case SHIPPED = 'SHIPPED';
    case DELIVERED = 'DELIVERED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
