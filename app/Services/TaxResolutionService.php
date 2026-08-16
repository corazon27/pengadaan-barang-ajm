<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaxResolutionState;
use App\Enums\TaxType;
use App\Models\OrderItem;
use App\Models\RuleSnapshot;
use App\Models\TaxRule;
use App\Values\AuthoritativeTaxResolution;
use App\Values\CommercialTaxContext;
use App\Values\TaxRuleResolution;
use App\Values\TaxRuleResolutionQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the two-stage authoritative tax resolution (Phase 2C).
 *
 * Stage 1 is the frozen CommercialTaxContext on the OrderItem. Stage 2 reads
 * that context (or, when $useOriginalContext is true, the reconstructible
 * original frozen context) and delegates rule selection to TaxRuleResolver.
 *
 * On a RESOLVED outcome the resolved TaxRule data is frozen into an immutable,
 * self-contained RuleSnapshot per line item. Non-authoritative outcomes
 * persist nothing and surface as a non-authoritative AuthoritativeTaxResolution
 * (requireAuthoritative() throws TaxResolutionNotAuthoritativeException).
 *
 * Never reads products.tax_rate_percentage and never performs tax math;
 * dpp_amount on the snapshot stays NULL in Phase 2C.
 */
class TaxResolutionService
{
    public function __construct(
        private readonly TaxRuleResolver $resolver,
    ) {}

    public function resolveForLineItem(
        OrderItem $orderItem,
        TaxType $taxType,
        Carbon $eventDate,
        ?string $taxpayerStatusFallback = null,
        bool $useOriginalContext = false,
    ): AuthoritativeTaxResolution {
        $context = $useOriginalContext
            ? ($orderItem->originalCommercialTaxContext() ?? $orderItem->commercialTaxContext())
            : $orderItem->commercialTaxContext();

        if ($context === null) {
            return $this->nonAuthoritative(
                TaxResolutionState::REVIEW_REQUIRED,
                'NO_COMMERCIAL_CONTEXT',
                'Order item has no frozen commercial tax context; authoritative resolution is impossible.',
                $this->placeholderContext(),
                $eventDate,
            );
        }

        $query = new TaxRuleResolutionQuery(
            taxType: $taxType,
            effectiveDate: $eventDate,
            taxpayerStatus: $context->taxpayerStatus ?? $taxpayerStatusFallback,
            buyerClassification: $context->buyerClassification,
            vatCollectorStatus: $context->collectorStatus,
            transactionType: $context->transactionType,
            productClassification: $context->productClassification,
        );

        $resolution = $this->resolver->resolve($query);

        if (! $resolution->isResolved() || $resolution->resolvedRule === null) {
            return $this->nonAuthoritative(
                $resolution->state,
                $resolution->reasonCode ?? 'UNRESOLVED',
                $resolution->reason,
                $context,
                $eventDate,
                $resolution,
            );
        }

        $snapshot = $this->persistSnapshot($orderItem, $context, $resolution->resolvedRule, $eventDate);

        return new AuthoritativeTaxResolution(
            state: TaxResolutionState::RESOLVED,
            resolution: $resolution,
            context: $context,
            eventDate: $eventDate,
            ruleSnapshot: $snapshot,
        );
    }

    private function persistSnapshot(
        OrderItem $orderItem,
        CommercialTaxContext $context,
        TaxRule $rule,
        Carbon $eventDate,
    ): RuleSnapshot {
        return DB::transaction(function () use ($orderItem, $context, $rule, $eventDate) {
            $identity = $context->orderTimeRuleIdentity();

            return RuleSnapshot::create([
                'order_item_id' => $orderItem->id,
                'tax_rule_id' => $rule->id,
                'rule_code' => $rule->rule_code,
                'rule_version' => $rule->rule_version,
                'tax_type' => $rule->tax_type,
                'taxpayer_status' => $context->taxpayerStatus,
                'buyer_classification' => $context->buyerClassification,
                'vat_collector_status' => $context->collectorStatus,
                'transaction_type' => $context->transactionType,
                'product_classification' => $context->productClassification,
                'resolution_date' => $eventDate->toDateString(),
                'effective_from' => $rule->effective_from,
                'effective_until' => $rule->effective_until,
                'dpp_amount' => null,
                'dpp_method_snapshot' => $rule->dpp_method,
                'dpp_formula_snapshot' => $rule->dpp_formula,
                'base_amount_definition_snapshot' => $rule->base_amount_definition,
                'unit_price_snapshot' => $context->unitPriceSnapshot,
                'quantity_snapshot' => $orderItem->quantity,
                'line_base_amount_snapshot' => $context->lineBaseAmountSnapshot,
                'statutory_rate_snapshot' => $rule->statutory_rate,
                'tax_formula_snapshot' => $rule->tax_formula,
                'effective_burden_snapshot' => $rule->effective_burden,
                'faktur_code' => $rule->faktur_code,
                'withholding_snapshot' => $rule->withholding_rule,
                'legal_reference' => $rule->legal_reference,
                'order_time_rule_id' => $identity['id'],
                'order_time_rule_code' => $identity['code'],
                'order_time_rule_version' => $identity['version'],
                'resolution_state' => TaxResolutionState::RESOLVED,
            ]);
        });
    }

    private function nonAuthoritative(
        TaxResolutionState $state,
        string $reasonCode,
        string $reason,
        CommercialTaxContext $context,
        Carbon $eventDate,
        ?TaxRuleResolution $resolution = null,
    ): AuthoritativeTaxResolution {
        return new AuthoritativeTaxResolution(
            state: $state,
            resolution: $resolution ?? new TaxRuleResolution(
                state: $state,
                resolvedRule: null,
                candidateCount: 0,
                conflictingRules: collect(),
                reasonCode: $reasonCode,
                reason: $reason,
            ),
            context: $context,
            eventDate: $eventDate,
        );
    }

    private function placeholderContext(): CommercialTaxContext
    {
        return new CommercialTaxContext(
            unitPriceSnapshot: '0',
            lineBaseAmountSnapshot: '0',
        );
    }
}
