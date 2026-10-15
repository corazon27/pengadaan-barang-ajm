<?php

declare(strict_types=1);

namespace App\Enums;

enum RfqStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case REVIEWED = 'REVIEWED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CONVERTED_TO_ORDER = 'CONVERTED_TO_ORDER';
    case QUOTED = 'QUOTED';
    case CANCELLED = 'CANCELLED';

    /**
     * Internal tracking label. These describe AJM's own workflow state and are
     * explicitly NOT an official INAPROC/SPSE approval status (GOV-04).
     */
    public function statusLabel(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Diajukan',
            self::REVIEWED => 'Ditinjau',
            self::QUOTED => 'Penawaran Tersedia',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::CONVERTED_TO_ORDER => 'Menjadi Pesanan',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
