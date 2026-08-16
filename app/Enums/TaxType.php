<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxType: string
{
    case PPN = 'PPN';
    case PPH = 'PPh';
    case PPNBM = 'PPNBM';
    case BEA_METERAI = 'BEA_METERAI';
}
