<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case CASH = 'CASH';
    case OTHERS = 'OTHERS';

    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Transfer Bank',
            self::CASH => 'Tunai',
            self::OTHERS => 'Lainnya',
        };
    }
}
