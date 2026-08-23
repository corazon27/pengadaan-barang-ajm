<?php

declare(strict_types=1);

namespace App\Enums;

enum LegalFunctionStatus: string
{
    case INACTIVE = 'INACTIVE';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case TERMINATED = 'TERMINATED';
}
