<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case UNPAID = 'UNPAID';
    case OVERDUE = 'OVERDUE';
    case PAID = 'PAID';
}
