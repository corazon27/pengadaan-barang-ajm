<?php

declare(strict_types=1);

namespace App\Enums;

enum LawfulBasis: string
{
    case CONSENT = 'CONSENT';
    case CONTRACT = 'CONTRACT';
    case LEGAL_OBLIGATION = 'LEGAL_OBLIGATION';
    case VITAL_INTEREST = 'VITAL_INTEREST';
    case PUBLIC_INTEREST = 'PUBLIC_INTEREST';
    case LEGITIMATE_INTEREST = 'LEGITIMATE_INTEREST';
}
