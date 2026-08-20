<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LawfulBasis;

/**
 * Resolves lawful basis for affected processing during DSR handling.
 * PDP-RIGHT-006: Automated-decision objection → require audit only when
 * context is clear + policy exists; otherwise REQUIRE_REVIEW + human.
 * No invented logic: uses DataInventory mapping + policy resolution.
 */
class LawfulBasisResolver
{
    public function __construct(
        private readonly DataInventory $inventory,
    ) {}

    /**
     * Resolve the processing lawful basis for a given user + processing class.
     *
     * @return array{basis: string|null, rule_id: string|null, requires_review: bool}
     */
    public function resolveForUser(string $processingClass): array
    {
        $basis = $this->inventory->resolve($processingClass);
        $ruleId = $this->inventory->ruleId($processingClass);

        if ($basis === null) {
            return [
                'basis' => null,
                'rule_id' => null,
                'requires_review' => true,
            ];
        }

        return [
            'basis' => $basis,
            'rule_id' => $ruleId,
            'requires_review' => false,
        ];
    }

    /**
     * Check if an automated-decision objection requires human review
     * (PDP-RIGHT-006: only deterministic when both context and policy are clear).
     */
    public function automatedDecisionRequiresReview(string $processingClass): bool
    {
        $result = $this->resolveForUser($processingClass);

        return $result['requires_review']
            || $result['basis'] !== LawfulBasis::LEGITIMATE_INTEREST->value;
    }
}
