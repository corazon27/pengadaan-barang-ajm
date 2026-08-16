<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outcome of a tax amount calculation (Phase 2D).
 *
 * RESOLVED         — a deterministic, authoritative tax amount was computed.
 * REVIEW_REQUIRED  — the calculation could not proceed authoritatively (e.g.
 *                    incomplete rule data, unsupported DPP method, unknown
 *                    formula); the transaction must be routed to human review.
 */
enum TaxCalculationState: string
{
    case RESOLVED = 'RESOLVED';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
}
