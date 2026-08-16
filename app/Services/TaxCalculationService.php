<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DppMethod;
use App\Enums\TaxCalculationState;
use App\Models\RuleSnapshot;
use App\Support\CalculationPolicy;
use App\Support\DecimalMath;
use App\Values\TaxCalculationInput;
use App\Values\TaxCalculationResult;
use InvalidArgumentException;

/**
 * Deterministic per-line tax amount calculation (Phase 2D).
 *
 * Authoritative chain (effective_burden is diagnostic only, never used to
 * compute the authoritative tax):
 *
 *   Base Amount → DPP Method / DPP Formula → DPP → Tax Formula → Tax Amount
 *
 * Formula evaluation is a closed whitelist with generic numerator/denominator
 * parsing (no eval, no arbitrary expressions, no hardcoded 11/12 business
 * rule). Monetary arithmetic uses DecimalMath (BC Math) at the scales and
 * rounding mode defined by CalculationPolicy. The service is pure: it reads
 * only the TaxCalculationInput and writes nothing.
 */
class TaxCalculationService
{
    public function __construct(
        private readonly CalculationPolicy $policy,
    ) {}

    public function calculate(TaxCalculationInput $input): TaxCalculationResult
    {
        if ($input->resolutionState !== TaxCalculationState::RESOLVED) {
            return $this->review('NON_AUTHORITATIVE_INPUT', $input, 'Snapshot is not an authoritative RESOLVED record.');
        }

        if (
            $input->dppMethod === null
            || $input->dppFormula === null
            || $input->taxFormula === null
            || $input->statutoryRate === null
            || $input->effectiveBurden === null
        ) {
            return $this->review('INCOMPLETE_RULE_DATA', $input, 'Snapshot lacks required rule data for calculation.');
        }

        if (DecimalMath::isNegative($input->baseAmount)) {
            throw new InvalidArgumentException('Negative base amount is not allowed.');
        }

        $dpp = $this->resolveDpp($input);
        if (! $dpp->isResolved()) {
            return $this->review($dpp->reasonCode, $input, $dpp->reason);
        }

        $tax = $this->resolveTax($input, $dpp->amount);
        if (! $tax->isResolved()) {
            return $this->review($tax->reasonCode, $input, $tax->reason);
        }

        $burdenAmount = DecimalMath::roundHalfUp(
            DecimalMath::div(
                DecimalMath::mul($input->baseAmount, $input->effectiveBurden, $this->policy->intermediateScale()),
                '100',
                $this->policy->intermediateScale(),
            ),
            $this->policy->moneyScale(),
        );

        $burdenConsistent = DecimalMath::compare(
            DecimalMath::sub($tax->amount, $burdenAmount, $this->policy->intermediateScale()),
            $this->policy->burdenTolerance(),
            $this->policy->intermediateScale(),
        ) <= 0 && DecimalMath::compare(
            DecimalMath::sub($burdenAmount, $tax->amount, $this->policy->intermediateScale()),
            $this->policy->burdenTolerance(),
            $this->policy->intermediateScale(),
        ) <= 0;

        return new TaxCalculationResult(
            state: TaxCalculationState::RESOLVED,
            reasonCode: 'RESOLVED',
            reason: "Resolved tax amount from rule {$input->ruleCode}/{$input->ruleVersion}.",
            orderItemId: $input->orderItemId,
            ruleSnapshotId: $input->ruleSnapshotId,
            ruleCode: $input->ruleCode,
            ruleVersion: $input->ruleVersion,
            baseAmount: $input->baseAmount,
            dppAmount: $dpp->amount,
            statutoryRate: $input->statutoryRate,
            taxAmount: $tax->amount,
            effectiveBurden: $input->effectiveBurden,
            burdenConsistent: $burdenConsistent,
            dppMethod: $input->dppMethod,
            dppFormulaUsed: $input->dppFormula,
            taxFormulaUsed: $input->taxFormula,
            roundingMode: $this->policy->roundingMode(),
            precision: $this->policy->moneyScale(),
            calculationVersion: $this->policy->calculationVersion(),
            inputFingerprint: $input->toFingerprint(),
        );
    }

    /**
     * Convenience: calculate from a persisted RuleSnapshot alone. Returns a
     * REVIEW_REQUIRED result (INCOMPLETE_RULE_DATA) when the snapshot is not
     * self-contained for calculation; never reads OrderItem state.
     */
    public function calculateForSnapshot(RuleSnapshot $snapshot): TaxCalculationResult
    {
        $input = TaxCalculationInput::fromSnapshot($snapshot);

        if ($input === null) {
            return new TaxCalculationResult(
                state: TaxCalculationState::REVIEW_REQUIRED,
                reasonCode: 'INCOMPLETE_RULE_DATA',
                reason: 'Snapshot is not self-contained for calculation (missing rule or monetary data).',
                orderItemId: $snapshot->order_item_id,
                ruleSnapshotId: $snapshot->id,
                ruleCode: $snapshot->rule_code,
                ruleVersion: $snapshot->rule_version,
                baseAmount: $snapshot->line_base_amount_snapshot ?? $snapshot->unit_price_snapshot,
                dppAmount: null,
                statutoryRate: $snapshot->statutory_rate_snapshot,
                taxAmount: null,
                effectiveBurden: $snapshot->effective_burden_snapshot,
                burdenConsistent: null,
                dppMethod: $snapshot->dpp_method_snapshot,
                dppFormulaUsed: $snapshot->dpp_formula_snapshot,
                taxFormulaUsed: $snapshot->tax_formula_snapshot,
                roundingMode: $this->policy->roundingMode(),
                precision: $this->policy->moneyScale(),
                calculationVersion: $this->policy->calculationVersion(),
                inputFingerprint: hash('sha256', $snapshot->id),
            );
        }

        return $this->calculate($input);
    }

    /**
     * Resolve the DPP amount from the DPP method and formula. Only the closed
     * whitelist is evaluated; the fraction (e.g. 11/12) is parsed generically
     * from the formula string, never hardcoded.
     *
     * @return DppResolution
     */
    private function resolveDpp(TaxCalculationInput $input): object
    {
        if ($input->dppMethod === DppMethod::LAINNYA) {
            return new DppResolution(false, null, 'UNSUPPORTED_DPP_METHOD', 'LAINNYA DPP method is unsupported; no deterministic formula exists.');
        }

        $formula = trim((string) $input->dppFormula);

        if ($formula === 'Base Amount') {
            return new DppResolution(true, DecimalMath::roundHalfUp($input->baseAmount, $this->policy->moneyScale()), 'RESOLVED', 'DPP equals base amount.');
        }

        if (preg_match('/^Base Amount x (\d+)\/(\d+)$/', $formula, $matches) === 1) {
            $numerator = $matches[1];
            $denominator = $matches[2];

            if (bccomp($denominator, '0', 0) === 0) {
                return new DppResolution(false, null, 'ZERO_RATE', 'DPP formula denominator is zero.');
            }

            $dpp = DecimalMath::roundHalfUp(
                DecimalMath::div(
                    DecimalMath::mul($input->baseAmount, $numerator, $this->policy->intermediateScale()),
                    $denominator,
                    $this->policy->intermediateScale(),
                ),
                $this->policy->moneyScale(),
            );

            return new DppResolution(true, $dpp, 'RESOLVED', 'DPP computed from base amount fraction.');
        }

        return new DppResolution(false, null, 'UNKNOWN_DPP_FORMULA', "Unknown DPP formula '{$input->dppFormula}'.");
    }

    /**
     * Resolve the tax amount from the DPP and the tax formula.
     *
     * @return DppResolution
     */
    private function resolveTax(TaxCalculationInput $input, string $dpp): object
    {
        $formula = trim((string) $input->taxFormula);

        if ($formula !== 'DPP x Statutory Rate') {
            return new DppResolution(false, null, 'UNKNOWN_TAX_FORMULA', "Unknown tax formula '{$input->taxFormula}'.");
        }

        if (bccomp((string) $input->statutoryRate, '0', 0) === 0) {
            return new DppResolution(false, null, 'ZERO_RATE', 'Statutory rate is zero.');
        }

        $tax = DecimalMath::roundHalfUp(
            DecimalMath::div(
                DecimalMath::mul($dpp, (string) $input->statutoryRate, $this->policy->intermediateScale()),
                '100',
                $this->policy->intermediateScale(),
            ),
            $this->policy->moneyScale(),
        );

        return new DppResolution(true, $tax, 'RESOLVED', 'Tax computed as DPP x statutory rate.');
    }

    private function review(string $reasonCode, TaxCalculationInput $input, string $reason): TaxCalculationResult
    {
        return new TaxCalculationResult(
            state: TaxCalculationState::REVIEW_REQUIRED,
            reasonCode: $reasonCode,
            reason: $reason,
            orderItemId: $input->orderItemId,
            ruleSnapshotId: $input->ruleSnapshotId,
            ruleCode: $input->ruleCode,
            ruleVersion: $input->ruleVersion,
            baseAmount: $input->baseAmount,
            dppAmount: null,
            statutoryRate: $input->statutoryRate,
            taxAmount: null,
            effectiveBurden: $input->effectiveBurden,
            burdenConsistent: null,
            dppMethod: $input->dppMethod,
            dppFormulaUsed: $input->dppFormula,
            taxFormulaUsed: $input->taxFormula,
            roundingMode: $this->policy->roundingMode(),
            precision: $this->policy->moneyScale(),
            calculationVersion: $this->policy->calculationVersion(),
            inputFingerprint: $input->toFingerprint(),
        );
    }
}

/**
 * @internal
 */
final class DppResolution
{
    public function __construct(
        public readonly bool $resolved,
        public readonly ?string $amount,
        public readonly ?string $reasonCode,
        public readonly string $reason,
    ) {}

    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
