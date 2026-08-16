<?php

declare(strict_types=1);

namespace App\Values;

use App\Enums\DppMethod;
use App\Enums\TaxCalculationState;
use App\Exceptions\TaxCalculationNotAuthoritativeException;

/**
 * Immutable outcome of a tax amount calculation (Phase 2D).
 *
 * RESOLVED results carry the authoritative DPP and tax amounts computed
 * through the closed-formula pipeline (Base → DPP → Tax). REVIEW_REQUIRED
 * results carry a reasonCode/reason and no amounts. effective_burden is a
 * diagnostic consistency signal only — it never drives the authoritative tax
 * amount. toArray() provides a stable, value-for-value surface for
 * determinism/reproducibility tests.
 */
final readonly class TaxCalculationResult
{
    public function __construct(
        public TaxCalculationState $state,
        public ?string $reasonCode,
        public string $reason,
        public ?string $orderItemId,
        public string $ruleSnapshotId,
        public string $ruleCode,
        public string $ruleVersion,
        public ?string $baseAmount,
        public ?string $dppAmount,
        public ?string $statutoryRate,
        public ?string $taxAmount,
        public ?string $effectiveBurden,
        public ?bool $burdenConsistent,
        public ?DppMethod $dppMethod,
        public ?string $dppFormulaUsed,
        public ?string $taxFormulaUsed,
        public string $roundingMode,
        public int $precision,
        public string $calculationVersion,
        public string $inputFingerprint,
    ) {}

    public function isResolved(): bool
    {
        return $this->state === TaxCalculationState::RESOLVED;
    }

    public function requiresReview(): bool
    {
        return ! $this->isResolved();
    }

    /**
     * @throws TaxCalculationNotAuthoritativeException
     */
    public function requireAuthoritative(): self
    {
        if ($this->requiresReview()) {
            throw new TaxCalculationNotAuthoritativeException(
                $this->state,
                $this->reasonCode ?? 'UNRESOLVED',
                $this->reason,
            );
        }

        return $this;
    }

    /**
     * Stable value-for-value representation used for determinism and
     * byte-for-byte reproducibility assertions.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'reasonCode' => $this->reasonCode,
            'reason' => $this->reason,
            'orderItemId' => $this->orderItemId,
            'ruleSnapshotId' => $this->ruleSnapshotId,
            'ruleCode' => $this->ruleCode,
            'ruleVersion' => $this->ruleVersion,
            'baseAmount' => $this->baseAmount,
            'dppAmount' => $this->dppAmount,
            'statutoryRate' => $this->statutoryRate,
            'taxAmount' => $this->taxAmount,
            'effectiveBurden' => $this->effectiveBurden,
            'burdenConsistent' => $this->burdenConsistent,
            'dppMethod' => $this->dppMethod?->value,
            'dppFormulaUsed' => $this->dppFormulaUsed,
            'taxFormulaUsed' => $this->taxFormulaUsed,
            'roundingMode' => $this->roundingMode,
            'precision' => $this->precision,
            'calculationVersion' => $this->calculationVersion,
            'inputFingerprint' => $this->inputFingerprint,
        ];
    }
}
