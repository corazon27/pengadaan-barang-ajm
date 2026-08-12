<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SUPERADMIN = 'SUPERADMIN';
    case BUYER_B2B = 'BUYER_B2B';
    case BUYER_B2G = 'BUYER_B2G';
}
