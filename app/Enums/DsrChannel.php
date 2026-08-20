<?php

declare(strict_types=1);

namespace App\Enums;

enum DsrChannel: string
{
    case WEB = 'WEB';
    case EMAIL = 'EMAIL';
    case IN_PERSON = 'IN_PERSON';
    case NON_ELEKTRONIK = 'NON_ELEKTRONIK';
    case API = 'API';
}
