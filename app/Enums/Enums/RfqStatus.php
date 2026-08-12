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
}
