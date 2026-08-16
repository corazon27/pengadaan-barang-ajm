<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case UNPAID = 'UNPAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case OVERDUE = 'OVERDUE';
    case PAID = 'PAID';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';

    public function statusLabel(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Dibayar',
            self::PARTIALLY_PAID => 'Dibayar Sebagian',
            self::OVERDUE => 'Terlambat',
            self::PAID => 'Lunas',
            self::REVIEW_REQUIRED => 'Menunggu Perhitungan Pajak',
        };
    }
}
