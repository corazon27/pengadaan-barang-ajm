<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaxType;
use App\Exceptions\TaxComputationHoldException;
use App\Models\Invoice;
use App\Models\InvoiceRuleSnapshot;
use App\Models\Order;
use App\Models\RuleSnapshot;
use App\Support\CalculationPolicy;
use App\Values\InvoiceTaxOutcome;
use App\Values\TaxCalculationResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies the authoritative tax engine to an invoice (Phase 2E).
 *
 * Orchestration only: per line item it resolves the rule (TaxResolutionService)
 * and computes the PPN amount (TaxCalculationService). On any non-authoritative
 * line the whole batch is rolled back to a savepoint — no orphan snapshots are
 * persisted — and a hold outcome is returned so the caller can flag the invoice
 * REVIEW_REQUIRED. Resolved lines persist the DPP back onto the immutable
 * snapshot and attach it to the invoice via the invoice_rule_snapshots junction.
 */
class InvoiceTaxService
{
    public function __construct(
        private readonly TaxResolutionService $resolutionService,
        private readonly TaxCalculationService $calculationService,
        private readonly CalculationPolicy $policy,
    ) {}

    public function applyToInvoice(Invoice $invoice, Order $order, Carbon $eventDate): InvoiceTaxOutcome
    {
        $items = $order->items()->get();
        $lineCount = $items->count();
        $resolvedLineCount = 0;
        $ppnAmount = '0.00';

        try {
            DB::transaction(function () use ($invoice, $items, $eventDate, &$ppnAmount, &$resolvedLineCount) {
                foreach ($items as $item) {
                    $resolution = $this->resolutionService->resolveForLineItem($item, TaxType::PPN, $eventDate);

                    if ($resolution->requiresReview()) {
                        throw new TaxComputationHoldException(
                            $resolution->resolution->reasonCode ?? 'UNRESOLVED',
                            "PPN resolution on hold for line {$item->id}.",
                        );
                    }

                    $result = $this->calculationService->calculateForSnapshot($resolution->ruleSnapshot);

                    if ($result->requiresReview()) {
                        throw new TaxComputationHoldException(
                            $result->reasonCode ?? 'UNRESOLVED',
                            "PPN calculation on hold for line {$item->id}.",
                        );
                    }

                    $this->attachSnapshot($invoice, $resolution->ruleSnapshot, $result);

                    $ppnAmount = bcadd($ppnAmount, $result->taxAmount, 2);
                    $resolvedLineCount++;
                }
            });
        } catch (TaxComputationHoldException $e) {
            return InvoiceTaxOutcome::hold(
                subtotal: (string) $invoice->subtotal,
                lineCount: $lineCount,
                resolvedLineCount: $resolvedLineCount,
                calculationVersion: $this->policy->calculationVersion(),
                holdReasonCode: $e->reasonCode,
            );
        }

        return InvoiceTaxOutcome::resolved(
            ppnAmount: $ppnAmount,
            subtotal: (string) $invoice->subtotal,
            lineCount: $lineCount,
            calculationVersion: $this->policy->calculationVersion(),
        );
    }

    /**
     * Persist the computed DPP onto the immutable RuleSnapshot (guarded raw
     * update, same transaction) and attach the snapshot to the invoice with
     * its authoritative tax amount.
     */
    private function attachSnapshot(Invoice $invoice, RuleSnapshot $snapshot, TaxCalculationResult $result): void
    {
        DB::table('rule_snapshots')
            ->where('id', $snapshot->id)
            ->whereNull('dpp_amount')
            ->update(['dpp_amount' => $result->dppAmount]);

        InvoiceRuleSnapshot::create([
            'invoice_id' => $invoice->id,
            'rule_snapshot_id' => $snapshot->id,
            'tax_amount' => $result->taxAmount,
        ]);
    }
}
