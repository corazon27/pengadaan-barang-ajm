<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaxApplicability;
use App\Enums\TaxResolutionState;
use App\Models\TaxRule;
use App\Values\TaxRuleResolution;
use App\Values\TaxRuleResolutionQuery;
use Illuminate\Support\Collection;

/**
 * Deterministic tax rule resolution engine (Phase 2B).
 *
 * Algorithm:
 *  1. Effective-date filtering (inclusive bounds, NULL = open-ended).
 *  2. Dimension matching (rule NULL field = wildcard; query NULL cannot match
 *     a rule that constrains the dimension).
 *  3. Specificity tier selection — counts only transactional constraint
 *     dimensions (tax_type, taxpayer_status, buyer_classification,
 *     vat_collector_status, transaction_type, product_classification), never
 *     metadata fields.
 *  4. Applicability gate applied on the maximum-specificity tier only — a
 *     lower-specificity REVIEW_REQUIRED/UNRESOLVED rule never blocks a
 *     higher-specificity CONFIRMED rule.
 *  5. Within a CONFIRMED max tier: single rule -> RESOLVED; multiple
 *     equal-specificity rules -> RULE_CONFLICT.
 *
 * No rule is ever selected by ID, created_at, newest record, or insertion
 * order. Overlapping versions of the same rule_code at max specificity are
 * treated as a conflict (data/version integrity risk). Never reads
 * products.tax_rate_percentage.
 */
class TaxRuleResolver
{
    /**
     * @var list<class-string<\BackedEnum>>
     */
    private const SPECIFICITY_DIMENSIONS = [
        'tax_type',
        'taxpayer_status',
        'buyer_classification',
        'vat_collector_status',
        'transaction_type',
        'product_classification',
    ];

    public function resolve(TaxRuleResolutionQuery $query): TaxRuleResolution
    {
        $matched = $this->matchedCandidates($query);

        if ($matched->isEmpty()) {
            return TaxRuleResolution::review(
                TaxResolutionState::REVIEW_REQUIRED,
                'NO_MATCHING_RULE',
                'No applicable tax rule found for the given transaction context.',
                0,
                collect(),
            );
        }

        $tier = $this->maximumSpecificityTier($matched);

        $nonConfirmed = $tier->first(
            fn (TaxRule $rule) => $rule->applicability !== TaxApplicability::CONFIRMED,
        );

        if ($nonConfirmed !== null) {
            return TaxRuleResolution::review(
                TaxResolutionState::REVIEW_REQUIRED,
                'APPLICABILITY_REVIEW_REQUIRED',
                "Applicable tax rule {$nonConfirmed->rule_code}/{$nonConfirmed->rule_version} requires review "
                    ."(applicability: {$nonConfirmed->applicability->value}).",
                $tier->count(),
                $tier,
            );
        }

        if ($tier->count() === 1) {
            return TaxRuleResolution::resolved($tier->first(), $tier->count());
        }

        return TaxRuleResolution::review(
            TaxResolutionState::RULE_CONFLICT,
            'RULE_CONFLICT',
            'Multiple equally-applicable tax rules at maximum specificity; no deterministic winner.',
            $tier->count(),
            $tier,
        );
    }

    /**
     * @return Collection<int, TaxRule>
     */
    private function matchedCandidates(TaxRuleResolutionQuery $query): Collection
    {
        return TaxRule::query()
            ->where('tax_type', $query->taxType->value)
            ->whereDate('effective_from', '<=', $query->effectiveDate->toDateString())
            ->where(fn ($builder) => $builder
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $query->effectiveDate->toDateString()))
            ->orderBy('rule_code')
            ->orderBy('rule_version')
            ->get()
            ->filter(fn (TaxRule $rule) => $this->matchesDimensions($query, $rule));
    }

    private function matchesDimensions(TaxRuleResolutionQuery $query, TaxRule $rule): bool
    {
        return $this->matches($rule->taxpayer_status, $query->taxpayerStatus)
            && $this->matches($rule->buyer_classification?->value, $query->buyerClassification?->value)
            && $this->matches($rule->vat_collector_status?->value, $query->vatCollectorStatus?->value)
            && $this->matches($rule->transaction_type, $query->transactionType)
            && $this->matches($rule->product_classification, $query->productClassification);
    }

    private function matches(?string $ruleValue, ?string $queryValue): bool
    {
        if ($ruleValue === null) {
            return true;
        }

        return $ruleValue === $queryValue;
    }

    /**
     * @param  Collection<int, TaxRule>  $matched
     * @return Collection<int, TaxRule>
     */
    private function maximumSpecificityTier(Collection $matched): Collection
    {
        $max = $matched->max(fn (TaxRule $rule) => $this->specificity($rule));

        return $matched->filter(fn (TaxRule $rule) => $this->specificity($rule) === $max)
            ->values();
    }

    private function specificity(TaxRule $rule): int
    {
        $count = 0;

        foreach (self::SPECIFICITY_DIMENSIONS as $dimension) {
            if ($rule->getAttribute($dimension) !== null) {
                $count++;
            }
        }

        return $count;
    }
}
