<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentTerm: string
{
    case IMMEDIATE = 'IMMEDIATE';
    case TOP_14 = 'TOP_14';
    case TOP_30 = 'TOP_30';
    case TOP_60 = 'TOP_60';

    /**
     * Number of days for the term. IMMEDIATE settles on the issued date.
     */
    public function days(): int
    {
        return match ($this) {
            self::IMMEDIATE => 0,
            self::TOP_14 => 14,
            self::TOP_30 => 30,
            self::TOP_60 => 60,
        };
    }

    public function statusLabel(): string
    {
        return match ($this) {
            self::IMMEDIATE => 'Bayar Langsung',
            self::TOP_14 => 'TOP 14 Hari',
            self::TOP_30 => 'TOP 30 Hari',
            self::TOP_60 => 'TOP 60 Hari',
        };
    }
}
