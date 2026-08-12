<?php

declare(strict_types=1);

namespace App\Enums;

enum BastStatus: string
{
    case PENDING_SIGNATURE = 'PENDING_SIGNATURE';
    case SIGNED = 'SIGNED';
}
