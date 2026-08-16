<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * External lifecycle of the PSE Electronic Certificate issued by PSrE
 * Indonesia (PSE-CERT-001, PP 71/2019 Ps 51(1),(3); Permenkominfo 11/2022
 * Ps 24(2), 25-26, 29(2)).
 *
 * This is the external certificate lifecycle only; internal verification
 * state is tracked separately via VerificationStatus. AJM never issues
 * certificates internally and never generates certificate serials.
 */
enum PseCertificateStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
}
