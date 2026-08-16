<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outcome of a tax rule resolution.
 *
 * Distinct from TaxApplicability: TaxApplicability describes a rule's own
 * applicability/verification state; TaxResolutionState describes the resolver
 * outcome for a given transaction context.
 *
 * RESOLVED        — a single deterministic rule was selected.
 * REVIEW_REQUIRED — no authoritative result; human review required.
 * RULE_CONFLICT   — multiple equally-applicable rules; no deterministic winner.
 * UNRESOLVED      — resolution not yet attempted.
 * PENDING_LEGAL_REVIEW — awaiting legal review before resolution completes.
 * APPLICABILITY_UNKNOWN — no legal basis currently establishes applicability.
 */
enum TaxResolutionState: string
{
    case RESOLVED = 'RESOLVED';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    case RULE_CONFLICT = 'RULE_CONFLICT';
    case UNRESOLVED = 'UNRESOLVED';
    case PENDING_LEGAL_REVIEW = 'PENDING_LEGAL_REVIEW';
    case APPLICABILITY_UNKNOWN = 'APPLICABILITY_UNKNOWN';
}
