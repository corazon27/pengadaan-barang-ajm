<?php

declare(strict_types=1);

namespace App\Enums;

enum LegalFunctionApplicability: string
{
    case PENDING_LEGAL_REVIEW = 'PENDING_LEGAL_REVIEW';
    case UNKNOWN = 'UNKNOWN';
    case LEGAL_REVIEW = 'LEGAL_REVIEW';
    case REQUIRED = 'REQUIRED';
    case NOT_REQUIRED = 'NOT_REQUIRED';
}
