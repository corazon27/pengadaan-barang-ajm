<?php

declare(strict_types=1);

namespace App\Enums;

enum HumanReviewType: string
{
    case TAX = 'TAX';
    case SUPPLIER_ELIGIBILITY = 'SUPPLIER_ELIGIBILITY';
    case PDP = 'PDP';
    case PSE = 'PSE';
    case CERTIFICATION = 'CERTIFICATION';
    case PROCUREMENT_CHANNEL = 'PROCUREMENT_CHANNEL';
    case REGULATORY_CHANGE = 'REGULATORY_CHANGE';
    case DOCUMENT = 'DOCUMENT';
    case GENERAL = 'GENERAL';
}
