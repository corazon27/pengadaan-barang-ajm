<?php

declare(strict_types=1);

namespace App\Enums;

enum BuyerClassification: string
{
    case REGULAR = 'REGULAR';
    case GOVERNMENT = 'GOVERNMENT';
    case BUMN = 'BUMN';
    case DESIGNATED_COLLECTOR = 'DESIGNATED_COLLECTOR';
    case OTHER = 'OTHER';
}
