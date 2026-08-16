<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Applicability of a tax rule / reference to a transaction.
 *
 * CONFIRMED           — source-cited, deterministic, applies.
 * REVIEW_REQUIRED     — human review required before authoritative use.
 * UNRESOLVED          — not yet determined (default).
 * PENDING_LEGAL_REVIEW— awaiting legal review (TAX-PPH/B2G preconditions).
 * APPLICABILITY_UNKNOWN — no legal basis currently establishes applicability.
 */
enum TaxApplicability: string
{
    case CONFIRMED = 'CONFIRMED';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    case UNRESOLVED = 'UNRESOLVED';
    case PENDING_LEGAL_REVIEW = 'PENDING_LEGAL_REVIEW';
    case APPLICABILITY_UNKNOWN = 'APPLICABILITY_UNKNOWN';
}
