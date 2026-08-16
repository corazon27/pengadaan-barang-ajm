<?php

declare(strict_types=1);

namespace App\Values;

use App\Enums\TaxResolutionState;
use App\Models\TaxRule;
use Illuminate\Support\Collection;

/**
 * Immutable outcome of a tax rule resolution.
 *
 * resolvedRule is set only when state is RESOLVED. conflictingRules carries the
 * maximum-specificity candidate tier in deterministic (rule_code, rule_version)
 * order whenever resolution did not select a single rule.
 */
final readonly class TaxRuleResolution
{
    public function __construct(
        public TaxResolutionState $state,
        public ?TaxRule $resolvedRule,
        public int $candidateCount,
        public Collection $conflictingRules,
        public ?string $reasonCode,
        public string $reason,
    ) {}

    public static function resolved(TaxRule $rule, int $candidateCount): self
    {
        return new self(
            TaxResolutionState::RESOLVED,
            $rule,
            $candidateCount,
            collect([$rule]),
            'RESOLVED',
            "Resolved to tax rule {$rule->rule_code}/{$rule->rule_version}.",
        );
    }

    public static function review(
        TaxResolutionState $state,
        string $reasonCode,
        string $reason,
        int $candidateCount,
        Collection $conflictingRules,
    ): self {
        return new self(
            $state,
            null,
            $candidateCount,
            $conflictingRules,
            $reasonCode,
            $reason,
        );
    }

    public function isResolved(): bool
    {
        return $this->state === TaxResolutionState::RESOLVED;
    }

    public function requiresReview(): bool
    {
        return ! $this->isResolved();
    }
}
