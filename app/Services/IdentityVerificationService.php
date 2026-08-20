<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IdentityConfidence;
use App\Enums\VerificationStatus;
use App\Models\User;

/**
 * Identity verification service for DSR operations.
 * §D: Always uses VerificationStatus binary verdict.
 * §E: Identity-confidence is separate (IdentityConfidence constant class).
 */
class IdentityVerificationService
{
    /**
     * Determine identity verification status for a DSR subject.
     *
     * PDP-RIGHT-001: for user-submitted DSRs, identity = PREVIOUSLY_VERIFIED
     * (auth-required, no additional evidence at submission).
     * Admin-created DSRs: UNVERIFIED.
     */
    public function determineSubmissionStatus(User $actor, ?User $subject): VerificationStatus
    {
        if ($subject !== null && $actor->id === $subject->id) {
            return VerificationStatus::VERIFIED;
        }

        return VerificationStatus::UNVERIFIED;
    }

    /**
     * Get identity confidence label for a given scenario.
     *
     * @return string One of IdentityConfidence::ALL
     */
    public function confidenceForSubmission(bool $isSelfSubmitted): string
    {
        if ($isSelfSubmitted) {
            return IdentityConfidence::AUTHENTICATED_ONLY;
        }

        return IdentityConfidence::EVIDENCE_REQUIRED;
    }

    /**
     * Check if additional evidence is required for the given verification level.
     */
    public function requiresAdditionalEvidence(string $confidence): bool
    {
        return in_array($confidence, [
            IdentityConfidence::EVIDENCE_REQUIRED,
            IdentityConfidence::VERIFICATION_FAILED,
        ], true);
    }
}
