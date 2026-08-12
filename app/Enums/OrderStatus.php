<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case DRAFT = 'DRAFT';
    case WAITING_PO = 'WAITING_PO';
    case PROCESSING = 'PROCESSING';
    case SHIPPED = 'SHIPPED';
    case BAST_SIGNED = 'BAST_SIGNED';
    case INVOICED = 'INVOICED';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';
}
