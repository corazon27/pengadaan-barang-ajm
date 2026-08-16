<?php

declare(strict_types=1);

namespace App\Enums;

enum DppMethod: string
{
    case NILAI_LAIN = 'NILAI_LAIN';
    case HARGA_JUAL = 'HARGA_JUAL';
    case LAINNYA = 'LAINNYA';
}
