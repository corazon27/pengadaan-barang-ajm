<?php

declare(strict_types=1);

namespace App\Enums;

enum SubjectType: string
{
    case USER = 'USER';
    case PROXY = 'PROXY';
    case EXTERNAL = 'EXTERNAL';
}
