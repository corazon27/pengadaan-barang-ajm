<?php

declare(strict_types=1);

namespace App\Enums;

enum HumanReviewStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case ESCALATED = 'ESCALATED';
    case RECORDED = 'RECORDED';
    case REQUEST_MORE_EVIDENCE = 'REQUEST_MORE_EVIDENCE';
}
