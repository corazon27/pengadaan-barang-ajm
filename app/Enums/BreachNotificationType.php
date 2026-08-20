<?php

declare(strict_types=1);

namespace App\Enums;

enum BreachNotificationType: string
{
    case AUTHORITY = 'AUTHORITY';
    case DATA_SUBJECT = 'DATA_SUBJECT';
    case SUPERVISOR = 'SUPERVISOR';
}
