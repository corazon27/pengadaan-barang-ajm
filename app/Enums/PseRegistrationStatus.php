<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of the PSE registration with the government (PSE-REG-001/002/003,
 * PP 71/2019 Ps 6(1), Permenkominfo 5/2020 jo 10/2021 Ps 4-6).
 *
 * Kept strictly separate from legal applicability (see
 * PseRegistrationApplicability): lifecycle records the external registration
 * state; applicability records the internal legal-review state.
 */
enum PseRegistrationStatus: string
{
    case UNREGISTERED = 'UNREGISTERED';
    case PENDING = 'PENDING';
    case REGISTERED = 'REGISTERED';
    case SUSPENDED = 'SUSPENDED';
    case EXPIRED = 'EXPIRED';
}
