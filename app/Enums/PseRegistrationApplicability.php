<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Internal legal applicability of the PSE registration rules to AJM,
 * separate from the registration lifecycle (PseRegistrationStatus).
 *
 * CONFIRMED             — source-cited, deterministic, applies.
 * REVIEW_REQUIRED       — human review required before authoritative use.
 * UNRESOLVED            — not yet determined (default).
 * PENDING_LEGAL_REVIEW  — awaiting legal review.
 * APPLICABILITY_UNKNOWN — no legal basis currently establishes applicability.
 */
enum PseRegistrationApplicability: string
{
    case CONFIRMED = 'CONFIRMED';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    case UNRESOLVED = 'UNRESOLVED';
    case PENDING_LEGAL_REVIEW = 'PENDING_LEGAL_REVIEW';
    case APPLICABILITY_UNKNOWN = 'APPLICABILITY_UNKNOWN';
}
