<?php

declare(strict_types=1);

namespace App\Enums;

enum DsrStatus: string
{
    case RECEIVED = 'RECEIVED';
    case IDENTITY_VERIFIED = 'IDENTITY_VERIFIED';
    case PROCESSING = 'PROCESSING';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    case FULFILLED = 'FULFILLED';
    case REJECTED = 'REJECTED';
    case CLOSED = 'CLOSED';
}
