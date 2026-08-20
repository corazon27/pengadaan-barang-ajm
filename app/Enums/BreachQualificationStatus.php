<?php

declare(strict_types=1);

namespace App\Enums;

enum BreachQualificationStatus: string
{
    case UNKNOWN = 'UNKNOWN';
    case REQUIRE_REVIEW = 'REQUIRE_REVIEW';
    case QUALIFIED = 'QUALIFIED';
    case NOT_QUALIFIED = 'NOT_QUALIFIED';
}
