<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING_VERIFICATION = 'PENDING_VERIFICATION';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';

    public function statusLabel(): string
    {
        return match ($this) {
            self::PENDING_VERIFICATION => 'Menunggu Verifikasi',
            self::VERIFIED => 'Terverifikasi',
            self::REJECTED => 'Ditolak',
        };
    }
}
