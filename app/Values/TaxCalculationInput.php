<?php

declare(strict_types=1);

namespace App\Values;

use App\Enums\BuyerClassification;
use App\Enums\DppMethod;
use App\Enums\TaxCalculationState;
use App\Enums\TaxResolutionState;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use App\Models\RuleSnapshot;
use App\Support\DecimalMath;

/**
 * Immutable input to TaxCalculationService (Phase 2D).
 *
 * Constructed exclusively from a RuleSnapshot — rule data, commercial
 * classifications and the immutable monetary inputs (unit_price, quantity,
 * line_base_amount) are all read from the snapshot itself. The calculation
 * path never reads current OrderItem state and never relies on
 * originalCommercialTaxContext() / audit-log reconstruction.
 */
final readonly class TaxCalculationInput
{
    public function __construct(
        public ?string $orderItemId,
        public string $ruleSnapshotId,
        public string $ruleCode,
        public string $ruleVersion,
        public TaxType $taxType,
        public ?DppMethod $dppMethod,
        public ?string $baseAmountDefinition,
        public ?string $dppFormula,
        public ?string $taxFormula,
        public ?string $statutoryRate,
        public ?string $effectiveBurden,
        public ?string $taxpayerStatus,
        public ?BuyerClassification $buyerClassification,
        public ?VatCollectorStatus $vatCollectorStatus,
        public ?string $transactionType,
        public ?string $productClassification,
        public ?string $unitPriceSnapshot,
        public ?int $quantitySnapshot,
        public ?string $lineBaseAmountSnapshot,
        public string $baseAmount,
        public string $resolutionDate,
        public TaxCalculationState $resolutionState,
    ) {}

    /**
     * Build from a persisted RuleSnapshot alone. Returns null when the
     * snapshot is not an authoritative RESOLVED record or lacks the required
     * rule/monetary data to be self-contained for calculation.
     *
     * Never reads OrderItem attributes; the snapshot is the canonical source.
     */
    public static function fromSnapshot(RuleSnapshot $snapshot): ?self
    {
        if ($snapshot->resolution_state !== TaxResolutionState::RESOLVED) {
            return null;
        }

        $unitPrice = $snapshot->unit_price_snapshot;
        $quantity = $snapshot->quantity_snapshot;
        $lineBaseAmount = $snapshot->line_base_amount_snapshot;

        $baseAmount = $lineBaseAmount !== null
            ? DecimalMath::normalize($lineBaseAmount, 2)
            : ($unitPrice !== null && $quantity !== null
                ? DecimalMath::mul($unitPrice, (string) $quantity, 2)
                : null);

        if (
            $snapshot->dpp_method_snapshot === null
            || $snapshot->dpp_formula_snapshot === null
            || $snapshot->tax_formula_snapshot === null
            || $snapshot->statutory_rate_snapshot === null
            || $snapshot->effective_burden_snapshot === null
            || $baseAmount === null
        ) {
            return null;
        }

        return new self(
            orderItemId: $snapshot->order_item_id,
            ruleSnapshotId: $snapshot->id,
            ruleCode: $snapshot->rule_code,
            ruleVersion: $snapshot->rule_version,
            taxType: $snapshot->tax_type,
            dppMethod: $snapshot->dpp_method_snapshot,
            baseAmountDefinition: $snapshot->base_amount_definition_snapshot,
            dppFormula: $snapshot->dpp_formula_snapshot,
            taxFormula: $snapshot->tax_formula_snapshot,
            statutoryRate: $snapshot->statutory_rate_snapshot,
            effectiveBurden: $snapshot->effective_burden_snapshot,
            taxpayerStatus: $snapshot->taxpayer_status,
            buyerClassification: $snapshot->buyer_classification,
            vatCollectorStatus: $snapshot->vat_collector_status,
            transactionType: $snapshot->transaction_type,
            productClassification: $snapshot->product_classification,
            unitPriceSnapshot: $unitPrice,
            quantitySnapshot: $quantity,
            lineBaseAmountSnapshot: $lineBaseAmount,
            baseAmount: $baseAmount,
            resolutionDate: $snapshot->resolution_date->toDateString(),
            resolutionState: TaxCalculationState::RESOLVED,
        );
    }

    /**
     * Canonical, deterministic fingerprint over the full input surface.
     */
    public function toFingerprint(): string
    {
        return hash('sha256', json_encode($this->canonicalFields(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function canonicalFields(): array
    {
        return [
            'ruleSnapshotId' => $this->ruleSnapshotId,
            'ruleCode' => $this->ruleCode,
            'ruleVersion' => $this->ruleVersion,
            'taxType' => $this->taxType->value,
            'dppMethod' => $this->dppMethod?->value,
            'baseAmountDefinition' => $this->baseAmountDefinition,
            'dppFormula' => $this->dppFormula,
            'taxFormula' => $this->taxFormula,
            'statutoryRate' => $this->statutoryRate,
            'effectiveBurden' => $this->effectiveBurden,
            'taxpayerStatus' => $this->taxpayerStatus,
            'buyerClassification' => $this->buyerClassification?->value,
            'vatCollectorStatus' => $this->vatCollectorStatus?->value,
            'transactionType' => $this->transactionType,
            'productClassification' => $this->productClassification,
            'unitPriceSnapshot' => $this->unitPriceSnapshot,
            'quantitySnapshot' => $this->quantitySnapshot,
            'lineBaseAmountSnapshot' => $this->lineBaseAmountSnapshot,
            'baseAmount' => $this->baseAmount,
            'resolutionDate' => $this->resolutionDate,
        ];
    }
}
