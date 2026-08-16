<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\BuyerClassification;
use App\Enums\TaxApplicability;
use App\Enums\TaxResolutionState;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use App\Exceptions\TaxResolutionNotAuthoritativeException;
use App\Models\AuditLog;
use App\Models\FakturCode;
use App\Models\Invoice;
use App\Models\InvoiceRuleSnapshot;
use App\Models\OrderItem;
use App\Models\RuleSnapshot;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\TaxCalculationService;
use App\Services\TaxResolutionService;
use App\Services\TaxRuleResolver;
use App\Values\CommercialTaxContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class RuleSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private TaxResolutionService $service;

    private TaxCalculationService $calculation;

    protected function setUp(): void
    {
        parent::setUp();

        FakturCode::factory()->create(['code' => '01']);

        $this->service = new TaxResolutionService(new TaxRuleResolver);
        $this->calculation = app(TaxCalculationService::class);
    }

    private function context(array $overrides = []): CommercialTaxContext
    {
        return new CommercialTaxContext(
            unitPriceSnapshot: $overrides['unit_price_snapshot'] ?? '1000.00',
            lineBaseAmountSnapshot: $overrides['line_base_amount_snapshot'] ?? '1000.00',
            productClassification: $overrides['product_classification'] ?? null,
            buyerClassification: $overrides['buyer_classification'] ?? null,
            collectorStatus: $overrides['collector_status'] ?? null,
            transactionType: $overrides['transaction_type'] ?? 'PENYERAHAN_BKP_JKP',
            taxpayerStatus: $overrides['taxpayer_status'] ?? 'PKP',
            orderTimeRuleId: $overrides['order_time_rule_id'] ?? null,
            orderTimeRuleCode: $overrides['order_time_rule_code'] ?? null,
            orderTimeRuleVersion: $overrides['order_time_rule_version'] ?? null,
        );
    }

    public function test_snapshot_is_created_from_resolved_rule(): void
    {
        $rule = TaxRule::factory()->create();
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $result = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));

        $this->assertTrue($result->isAuthoritative());
        $this->assertSame($rule->id, $result->ruleSnapshot->tax_rule_id);
        $this->assertSame($item->id, $result->ruleSnapshot->order_item_id);
        $this->assertSame(TaxResolutionState::RESOLVED, $result->ruleSnapshot->resolution_state);
    }

    public function test_review_required_cannot_create_authoritative_snapshot(): void
    {
        TaxRule::factory()->create([
            'applicability' => TaxApplicability::REVIEW_REQUIRED,
            'statutory_rate' => null,
            'effective_burden' => null,
        ]);
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $result = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $result->state);
        $this->assertFalse($result->isAuthoritative());
        $this->assertNull($result->ruleSnapshot);
        $this->assertSame(0, RuleSnapshot::count());

        $this->expectException(TaxResolutionNotAuthoritativeException::class);
        $result->requireAuthoritative();
    }

    public function test_rule_conflict_cannot_create_authoritative_snapshot(): void
    {
        TaxRule::factory()->create(['rule_code' => 'TAX-PPN-05']);
        TaxRule::factory()->create(['rule_code' => 'TAX-PPN-06']);
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $result = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));

        $this->assertSame(TaxResolutionState::RULE_CONFLICT, $result->state);
        $this->assertFalse($result->isAuthoritative());
        $this->assertNull($result->ruleSnapshot);
        $this->assertSame(0, RuleSnapshot::count());

        $this->expectException(TaxResolutionNotAuthoritativeException::class);
        $result->requireAuthoritative();
    }

    public function test_multiple_line_items_produce_distinct_snapshots(): void
    {
        $regular = TaxRule::factory()->create(['buyer_classification' => BuyerClassification::REGULAR]);
        $government = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-07',
            'buyer_classification' => BuyerClassification::GOVERNMENT,
        ]);

        $itemA = OrderItem::factory()->create();
        $itemA->freezeCommercialTaxContext($this->context(['buyer_classification' => BuyerClassification::REGULAR]));

        $itemB = OrderItem::factory()->create();
        $itemB->freezeCommercialTaxContext($this->context(['buyer_classification' => BuyerClassification::GOVERNMENT]));

        $this->service->resolveForLineItem($itemA, TaxType::PPN, Carbon::parse('2025-03-01'));
        $this->service->resolveForLineItem($itemB, TaxType::PPN, Carbon::parse('2025-03-01'));

        $this->assertSame(2, RuleSnapshot::count());

        $snapshotA = RuleSnapshot::where('order_item_id', $itemA->id)->first();
        $snapshotB = RuleSnapshot::where('order_item_id', $itemB->id)->first();

        $this->assertSame($regular->id, $snapshotA->tax_rule_id);
        $this->assertSame($government->id, $snapshotB->tax_rule_id);
        $this->assertNotSame($snapshotA->id, $snapshotB->id);
    }

    public function test_same_rule_reused_across_line_items_keeps_single_snapshot_each(): void
    {
        $rule = TaxRule::factory()->create();

        $itemA = OrderItem::factory()->create();
        $itemA->freezeCommercialTaxContext($this->context());

        $itemB = OrderItem::factory()->create();
        $itemB->freezeCommercialTaxContext($this->context());

        $this->service->resolveForLineItem($itemA, TaxType::PPN, Carbon::parse('2025-03-01'));
        $this->service->resolveForLineItem($itemB, TaxType::PPN, Carbon::parse('2025-03-01'));

        $this->assertSame(2, RuleSnapshot::count());
        $this->assertSame(2, RuleSnapshot::where('tax_rule_id', $rule->id)->count());
        $this->assertSame(
            [$itemA->id, $itemB->id],
            RuleSnapshot::where('tax_rule_id', $rule->id)->orderBy('order_item_id')->pluck('order_item_id')->all()
        );
    }

    public function test_snapshot_is_immutable_after_creation(): void
    {
        $snapshot = RuleSnapshot::factory()->create();

        $this->expectException(LogicException::class);
        $snapshot->update(['statutory_rate_snapshot' => '15.0000']);
    }

    public function test_snapshot_delete_is_forbidden(): void
    {
        $snapshot = RuleSnapshot::factory()->create();

        $this->expectException(LogicException::class);
        $snapshot->delete();
    }

    public function test_historical_snapshot_unchanged_after_tax_rule_changes(): void
    {
        $rule = TaxRule::factory()->create();
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));

        $snapshot = RuleSnapshot::sole();

        $rule->update(['statutory_rate' => '15.0000', 'effective_burden' => '14.0000']);

        $snapshot->refresh();

        $this->assertSame('12.0000', $snapshot->statutory_rate_snapshot);
        $this->assertSame('11.0000', $snapshot->effective_burden_snapshot);
    }

    public function test_legacy_product_tax_rate_never_affects_snapshot(): void
    {
        TaxRule::factory()->create(['statutory_rate' => '12.0000', 'effective_burden' => '11.0000']);
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $result = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));

        $this->assertTrue($result->isAuthoritative());
        $this->assertSame('12.0000', $result->ruleSnapshot->statutory_rate_snapshot);
        $this->assertNotSame('99.50', $result->ruleSnapshot->statutory_rate_snapshot);
    }

    public function test_resolution_is_deterministic_across_repeats(): void
    {
        $rule = TaxRule::factory()->create();
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $first = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));
        $second = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));

        $this->assertTrue($first->isAuthoritative());
        $this->assertTrue($second->isAuthoritative());
        $this->assertSame($rule->id, $first->ruleSnapshot->tax_rule_id);
        $this->assertSame($rule->id, $second->ruleSnapshot->tax_rule_id);
        $this->assertSame(2, RuleSnapshot::count());
    }

    public function test_context_accessor_round_trips_frozen_values(): void
    {
        $item = OrderItem::factory()->create();
        $context = $this->context([
            'unit_price_snapshot' => '2500.00',
            'line_base_amount_snapshot' => '2500.00',
            'buyer_classification' => BuyerClassification::GOVERNMENT,
            'collector_status' => VatCollectorStatus::VERIFIED,
            'transaction_type' => 'SELF_CONSUMPTION',
            'taxpayer_status' => 'NON_PKP',
        ]);
        $item->freezeCommercialTaxContext($context);

        $reconstructed = $item->commercialTaxContext();

        $this->assertNotNull($reconstructed);
        $this->assertSame('2500.00', $reconstructed->unitPriceSnapshot);
        $this->assertSame('2500.00', $reconstructed->lineBaseAmountSnapshot);
        $this->assertSame(BuyerClassification::GOVERNMENT, $reconstructed->buyerClassification);
        $this->assertSame(VatCollectorStatus::VERIFIED, $reconstructed->collectorStatus);
        $this->assertSame('SELF_CONSUMPTION', $reconstructed->transactionType);
        $this->assertSame('NON_PKP', $reconstructed->taxpayerStatus);
    }

    public function test_context_is_frozen_once(): void
    {
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $this->expectException(LogicException::class);
        $item->freezeCommercialTaxContext($this->context(['taxpayer_status' => 'NON_PKP']));
    }

    public function test_direct_mutation_after_freeze_throws(): void
    {
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $this->expectException(LogicException::class);
        $item->buyer_classification_snapshot = BuyerClassification::GOVERNMENT->value;
        $item->save();
    }

    public function test_amendment_preserves_original_context_and_is_audited(): void
    {
        $item = OrderItem::factory()->create();
        $actor = User::factory()->create();
        $item->freezeCommercialTaxContext(
            $this->context(['buyer_classification' => BuyerClassification::GOVERNMENT]),
            $actor,
        );

        $item->amendCommercialTaxContext(
            $this->context(['buyer_classification' => BuyerClassification::REGULAR]),
            'Buyer corrected to REGULAR after data verification.',
            $actor,
        );

        $current = $item->commercialTaxContext();
        $original = $item->originalCommercialTaxContext();

        $this->assertSame(BuyerClassification::REGULAR, $current?->buyerClassification);
        $this->assertSame(BuyerClassification::GOVERNMENT, $original?->buyerClassification);

        $frozenAudit = AuditLog::where('entity_type', 'OrderItem')
            ->where('entity_id', $item->id)
            ->where('action', AuditAction::ORDER_COMMERCIAL_CONTEXT_FROZEN->value)
            ->sole();
        $amendAudit = AuditLog::where('entity_type', 'OrderItem')
            ->where('entity_id', $item->id)
            ->where('action', AuditAction::ORDER_COMMERCIAL_CONTEXT_AMENDED->value)
            ->sole();

        $this->assertSame([], $frozenAudit->previous_state);
        $this->assertSame(BuyerClassification::GOVERNMENT->value, $frozenAudit->new_state['buyer_classification_snapshot']);
        $this->assertSame(BuyerClassification::GOVERNMENT->value, $amendAudit->previous_state['buyer_classification_snapshot']);
        $this->assertSame(BuyerClassification::REGULAR->value, $amendAudit->new_state['buyer_classification_snapshot']);
        $this->assertSame('Buyer corrected to REGULAR after data verification.', $amendAudit->new_state['reason']);
        $this->assertSame($actor->id, $frozenAudit->user_id);
        $this->assertSame($actor->id, $amendAudit->user_id);
    }

    public function test_amend_requires_freeze_first(): void
    {
        $item = OrderItem::factory()->create();

        $this->expectException(LogicException::class);
        $item->amendCommercialTaxContext($this->context(), 'No-op.');
    }

    public function test_use_original_context_resolves_against_original_not_amended(): void
    {
        TaxRule::factory()->create(['buyer_classification' => BuyerClassification::GOVERNMENT]);

        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context(['buyer_classification' => BuyerClassification::GOVERNMENT]));
        $item->amendCommercialTaxContext(
            $this->context(['buyer_classification' => BuyerClassification::REGULAR]),
            'Buyer corrected.',
        );

        $usingOriginal = $this->service->resolveForLineItem(
            $item,
            TaxType::PPN,
            Carbon::parse('2025-03-01'),
            useOriginalContext: true,
        );
        $usingCurrent = $this->service->resolveForLineItem(
            $item,
            TaxType::PPN,
            Carbon::parse('2025-03-01'),
        );

        $this->assertTrue($usingOriginal->isAuthoritative());
        $this->assertFalse($usingCurrent->isAuthoritative());
        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $usingCurrent->state);
    }

    public function test_invoice_junction_links_invoice_and_snapshot(): void
    {
        $invoice = Invoice::factory()->create();
        $snapshot = RuleSnapshot::factory()->create();

        InvoiceRuleSnapshot::create([
            'invoice_id' => $invoice->id,
            'rule_snapshot_id' => $snapshot->id,
        ]);

        $this->assertTrue($invoice->ruleSnapshots()->whereKey($snapshot->id)->exists());
        $this->assertTrue($snapshot->invoices()->whereKey($invoice->id)->exists());
        $this->assertSame(1, $invoice->ruleSnapshots()->count());
        $this->assertNull($invoice->ruleSnapshots()->first()->pivot->tax_amount);
        $this->assertNotNull($invoice->ruleSnapshots()->first()->pivot->created_at);
    }

    public function test_invoice_junction_duplicate_is_rejected(): void
    {
        $invoice = Invoice::factory()->create();
        $snapshot = RuleSnapshot::factory()->create();

        InvoiceRuleSnapshot::create([
            'invoice_id' => $invoice->id,
            'rule_snapshot_id' => $snapshot->id,
        ]);

        $this->expectException(QueryException::class);
        InvoiceRuleSnapshot::create([
            'invoice_id' => $invoice->id,
            'rule_snapshot_id' => $snapshot->id,
        ]);
    }

    public function test_snapshot_requires_order_item(): void
    {
        $this->expectException(QueryException::class);

        RuleSnapshot::factory()->create(['order_item_id' => null]);
    }

    public function test_snapshot_is_self_contained(): void
    {
        $rule = TaxRule::factory()->create([
            'buyer_classification' => BuyerClassification::GOVERNMENT,
            'vat_collector_status' => VatCollectorStatus::UNVERIFIED,
            'transaction_type' => 'SELF_CONSUMPTION',
            'product_classification' => 'DIBEBASKAN',
        ]);

        $item = OrderItem::factory()->create();
        $context = $this->context([
            'buyer_classification' => BuyerClassification::GOVERNMENT,
            'collector_status' => VatCollectorStatus::UNVERIFIED,
            'transaction_type' => 'SELF_CONSUMPTION',
            'product_classification' => 'DIBEBASKAN',
            'order_time_rule_id' => $rule->id,
            'order_time_rule_code' => $rule->rule_code,
            'order_time_rule_version' => $rule->rule_version,
        ]);
        $item->freezeCommercialTaxContext($context);

        $result = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));
        $snapshot = $result->ruleSnapshot;

        $this->assertSame('PKP', $snapshot->taxpayer_status);
        $this->assertSame(BuyerClassification::GOVERNMENT, $snapshot->buyer_classification);
        $this->assertSame(VatCollectorStatus::UNVERIFIED, $snapshot->vat_collector_status);
        $this->assertSame('SELF_CONSUMPTION', $snapshot->transaction_type);
        $this->assertSame('DIBEBASKAN', $snapshot->product_classification);
        $this->assertSame($rule->rule_code, $snapshot->rule_code);
        $this->assertSame($rule->rule_version, $snapshot->rule_version);
        $this->assertSame($rule->faktur_code, $snapshot->faktur_code);
        $this->assertSame($rule->legal_reference, $snapshot->legal_reference);
        $this->assertSame($rule->statutory_rate, $snapshot->statutory_rate_snapshot);
        $this->assertSame($rule->effective_burden, $snapshot->effective_burden_snapshot);
        $this->assertSame($rule->effective_from->toDateString(), $snapshot->effective_from->toDateString());
        $this->assertSame('2025-03-01', $snapshot->resolution_date->toDateString());
        $this->assertNull($snapshot->dpp_amount);
        $this->assertSame($rule->id, $snapshot->order_time_rule_id);
        $this->assertSame($rule->rule_code, $snapshot->order_time_rule_code);
        $this->assertSame($rule->rule_version, $snapshot->order_time_rule_version);
    }

    public function test_order_time_taxpayer_status_wins_over_live_fallback(): void
    {
        TaxRule::factory()->create(['taxpayer_status' => 'NON_PKP']);

        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context(['taxpayer_status' => 'PKP']));

        $result = $this->service->resolveForLineItem(
            $item,
            TaxType::PPN,
            Carbon::parse('2025-03-01'),
            taxpayerStatusFallback: 'NON_PKP',
        );

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $result->state);
        $this->assertSame('NO_MATCHING_RULE', $result->resolution->reasonCode);
        $this->assertSame(0, RuleSnapshot::count());
    }

    public function test_no_frozen_context_is_non_authoritative(): void
    {
        $item = OrderItem::factory()->create();

        $result = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $result->state);
        $this->assertSame('NO_COMMERCIAL_CONTEXT', $result->resolution->reasonCode);
        $this->assertFalse($result->isAuthoritative());
        $this->assertSame(0, RuleSnapshot::count());

        $this->expectException(TaxResolutionNotAuthoritativeException::class);
        $result->requireAuthoritative();
    }

    public function test_snapshot_dpp_amount_is_write_once_and_immutable(): void
    {
        $rule = TaxRule::factory()->create();
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $result = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));
        $this->assertTrue($result->isAuthoritative());

        $snapshot = $result->ruleSnapshot;

        $this->assertNull($snapshot->dpp_amount);

        $this->expectException(LogicException::class);
        $snapshot->update(['dpp_amount' => '2000.00']);
    }

    public function test_dpp_amount_write_once_integration_simulation(): void
    {
        $rule = TaxRule::factory()->create();
        $item = OrderItem::factory()->create();
        $item->freezeCommercialTaxContext($this->context());

        $result = $this->service->resolveForLineItem($item, TaxType::PPN, Carbon::parse('2025-03-01'));
        $this->assertTrue($result->isAuthoritative());

        $snapshot = $result->ruleSnapshot;
        $this->assertNull($snapshot->dpp_amount);

        $computed = $this->calculation->calculateForSnapshot($snapshot);
        $this->assertFalse($computed->requiresReview());
        $dpp = $computed->dppAmount;

        $originalStatRate = $snapshot->fresh()->statutory_rate_snapshot;
        $originalBurden = $snapshot->fresh()->effective_burden_snapshot;
        $originalFakturCode = $snapshot->fresh()->faktur_code;

        DB::table('rule_snapshots')
            ->where('id', $snapshot->id)
            ->whereNull('dpp_amount')
            ->update(['dpp_amount' => $dpp]);

        $this->assertSame($dpp, $snapshot->fresh()->dpp_amount);
        $this->assertSame($originalStatRate, $snapshot->fresh()->statutory_rate_snapshot);
        $this->assertSame($originalBurden, $snapshot->fresh()->effective_burden_snapshot);
        $this->assertSame($originalFakturCode, $snapshot->fresh()->faktur_code);

        $affected = DB::table('rule_snapshots')
            ->where('id', $snapshot->id)
            ->whereNull('dpp_amount')
            ->update(['dpp_amount' => '9999.99']);

        $this->assertSame(0, $affected);
        $this->assertSame($dpp, $snapshot->fresh()->dpp_amount);
    }
}
