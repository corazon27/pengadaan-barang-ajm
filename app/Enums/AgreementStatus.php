<?php

declare(strict_types=1);

namespace App\Enums;

enum AgreementStatus: string
{
    case DRAFT = 'DRAFT';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case EXPIRED = 'EXPIRED';
    case TERMINATED = 'TERMINATED';
}
