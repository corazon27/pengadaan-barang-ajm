<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentStatus: string
{
    case ACTIVE = 'ACTIVE';
    case WITHDRAWN = 'WITHDRAWN';
    case EXPIRED = 'EXPIRED';
    case SUPERSEDED = 'SUPERSEDED';
    case INVALID = 'INVALID';
}
