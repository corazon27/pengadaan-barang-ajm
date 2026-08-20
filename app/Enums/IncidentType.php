<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentType: string
{
    case PSE_DISRUPTION = 'PSE_DISRUPTION';
    case PDP_FAILURE = 'PDP_FAILURE';
    case SECURITY_INCIDENT = 'SECURITY_INCIDENT';
    case OTHER = 'OTHER';
}
