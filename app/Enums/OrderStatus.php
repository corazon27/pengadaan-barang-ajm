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

    /**
     * Internal tracking label. These describe AJM's own fulfilment workflow and
     * are explicitly NOT an official INAPROC/SPSE status (GOV-04).
     */
    public function statusLabel(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Menunggu Pembayaran',
            self::PROCESSING => 'Diproses',
            self::SHIPPED => 'Dikirim',
            self::DELIVERED => 'Telah Diterima',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
