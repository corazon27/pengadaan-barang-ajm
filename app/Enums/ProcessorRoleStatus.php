<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessorRoleStatus: string
{
    case UNKNOWN = 'UNKNOWN';
    case REQUIRE_REVIEW = 'REQUIRE_REVIEW';
    case PROCESSOR = 'PROCESSOR';
    case NOT_PROCESSOR = 'NOT_PROCESSOR';
}
