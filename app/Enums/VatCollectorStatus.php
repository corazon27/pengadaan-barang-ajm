<?php

declare(strict_types=1);

namespace App\Enums;

enum VatCollectorStatus: string
{
    case VERIFIED = 'VERIFIED';
    case UNVERIFIED = 'UNVERIFIED';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
}
