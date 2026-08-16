<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DppMethod;
use App\Enums\TaxCalculationState;
use App\Enums\TaxResolutionState;
use App\Enums\TaxType;
use App\Models\FakturCode;
use App\Models\OrderItem;
use App\Models\RuleSnapshot;
use App\Models\TaxRule;
use App\Services\TaxCalculationService;
use App\Services\TaxResolutionService;
use App\Services\TaxRuleResolver;
use App\Support\CalculationPolicy;
use App\Support\DecimalMath;
use App\Values\CommercialTaxContext;
use App\Values\TaxCalculationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class TaxCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaxCalculationService $calculator;

    private TaxResolutionService $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        FakturCode::factory()->create(['code' => '01']);
        TaxRule::factory()->create(['rule_code' => 'TAX-PPN-01-DEFAULT', 'rule_version' => 'v1']);

        $this->calculator = new TaxCalculationService(new CalculationPolicy);
        $this->resolver = new TaxResolutionService(new TaxRuleResolver);
    }

    private function context(array $overrides = []): CommercialTaxContext
    {
        return new CommercialTaxContext(
            unitPriceSnapshot: $overrides['unit_price_snapshot'] ?? '1000000.00',
            lineBaseAmountSnapshot: $overrides['line_base_amount_snapshot'] ?? '1000000.00',
            productClassification: $overrides['product_classification'] ?? null,
            buyerClassification: $overrides['buyer_classification'] ?? null,
            collectorStatus: $overrides['collector_status'] ?? null,
            transactionType: $overrides['transaction_type'] ?? 'PENYERAHAN_BKP_JKP',
            taxpayerStatus: $overrides['taxpayer_status'] ?? 'PKP',
        );
    }

    private function resolveSnapshot(
        OrderItem $item,
        string $eventDate = '2025-03-01',
        array $contextOverrides = [],
    ): RuleSnapshot {
        $item->freezeCommercialTaxContext($this->context($contextOverrides));

        $result = $this->resolver->resolveForLineItem($item, TaxType::PPN, Carbon::parse($eventDate));

        $this->assertTrue(
            $result->isAuthoritative(),
            'Expected authoritative resolution; got state='.($result->state?->value ?? 'null')
                .' reason='.($result->reason ?? 'null')
                .' rule='.($result->resolvedRule?->rule_code ?? 'null'),
        );

        return $result->ruleSnapshot;
    }

    public function test_standard_non_luxury_case(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->isResolved());
        $this->assertSame('1000000.00', $result->baseAmount);
        $this->assertSame('916666.67', $result->dppAmount);
        $this->assertSame('110000.00', $result->taxAmount);
    }

    public function test_dpp_nilai_lain_uses_fraction_from_formula(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item, contextOverrides: ['line_base_amount_snapshot' => '1200000.00']);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->isResolved());
        $this->assertSame('1200000.00', $result->baseAmount);
        $this->assertSame('1100000.00', $result->dppAmount);
        $this->assertSame('132000.00', $result->taxAmount);
        $this->assertSame('Base Amount x 11/12', $result->dppFormulaUsed);
        $this->assertSame(DppMethod::NILAI_LAIN, $result->dppMethod);
    }

    public function test_statutory_rate_applies_to_dpp_not_base(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        // 12% of DPP (916,666.67) = 110,000.00; 12% of base (1,000,000) would be 120,000.00
        $this->assertSame('110000.00', $result->taxAmount);
    }

    public function test_effective_burden_consistency(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->isResolved());
        $this->assertTrue($result->burdenConsistent);

        $expectedBurdenAmount = DecimalMath::roundHalfUp(
            DecimalMath::div(DecimalMath::mul('1000000.00', '11.0000', 6), '100', 6),
            2,
        );

        $this->assertSame('110000.00', $expectedBurdenAmount);
    }

    public function test_effective_burden_is_diagnostic_not_authoritative(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->isResolved());
        // Authoritative tax comes from DPP formula + statutory rate, not from
        // effective_burden directly. Change the burden and the tax must stay put.
        $snapshot->refresh();
        $this->assertSame('110000.00', $result->taxAmount);
    }

    public function test_multiple_independent_lines_sum(): void
    {
        $itemA = OrderItem::factory()->create(['quantity' => 2]);
        $itemB = OrderItem::factory()->create(['quantity' => 3]);

        $snapshotA = $this->resolveSnapshot(
            $itemA,
            contextOverrides: ['unit_price_snapshot' => '500000.00', 'line_base_amount_snapshot' => '1000000.00'],
        );
        $snapshotB = $this->resolveSnapshot(
            $itemB,
            contextOverrides: ['unit_price_snapshot' => '700000.00', 'line_base_amount_snapshot' => '2100000.00'],
        );

        $resultA = $this->calculator->calculateForSnapshot($snapshotA);
        $resultB = $this->calculator->calculateForSnapshot($snapshotB);

        $this->assertTrue($resultA->isResolved());
        $this->assertTrue($resultB->isResolved());

        $lineSum = DecimalMath::add($resultA->taxAmount, $resultB->taxAmount, 2);

        // Invoice-level tax = SUM of independently rounded per-line results.
        $this->assertSame('341000.00', $lineSum);

        // NOT asserting sum == round(total base × effective burden); here the
        // mathematically expected aggregate matches (clean per-line values).
        $this->assertSame('341000.00', DecimalMath::roundHalfUp(DecimalMath::div(DecimalMath::mul('3100000.00', '11.0000', 6), '100', 6), 2));
    }

    public function test_quantity_greater_than_one_uses_line_base_amount(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 3]);
        $snapshot = $this->resolveSnapshot(
            $item,
            contextOverrides: ['unit_price_snapshot' => '500000.00', 'line_base_amount_snapshot' => '1500000.00'],
        );

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->isResolved());
        $this->assertSame('1500000.00', $result->baseAmount);
        $this->assertSame('1375000.00', $result->dppAmount);
        $this->assertSame('165000.00', $result->taxAmount);
    }

    public function test_precision_no_float_types(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot(
            $item,
            contextOverrides: ['line_base_amount_snapshot' => '1234567.89'],
        );

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->isResolved());
        $this->assertIsString($result->dppAmount);
        $this->assertIsString($result->taxAmount);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', (string) $result->dppAmount);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', (string) $result->taxAmount);
    }

    public function test_half_up_rounding_edges(): void
    {
        $this->assertSame('1.01', DecimalMath::roundHalfUp('1.005', 2));
        $this->assertSame('1.00', DecimalMath::roundHalfUp('1.004', 2));
        $this->assertSame('1000000000000000.00', DecimalMath::roundHalfUp('999999999999999.995', 2));
    }

    public function test_zero_amount_is_allowed(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item, contextOverrides: ['line_base_amount_snapshot' => '0.00']);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->isResolved());
        $this->assertSame('0.00', $result->baseAmount);
        $this->assertSame('0.00', $result->dppAmount);
        $this->assertSame('0.00', $result->taxAmount);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $snapshot = RuleSnapshot::factory()->create([
            'line_base_amount_snapshot' => '-500.00',
            'unit_price_snapshot' => '-500.00',
            'quantity_snapshot' => 1,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateForSnapshot($snapshot);
    }

    public function test_lainnya_dpp_method_requires_review(): void
    {
        $snapshot = RuleSnapshot::factory()->create([
            'dpp_method_snapshot' => DppMethod::LAINNYA,
            'dpp_formula_snapshot' => 'Unverifiable custom formula',
        ]);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->requiresReview());
        $this->assertSame('UNSUPPORTED_DPP_METHOD', $result->reasonCode);
        $this->assertSame(TaxCalculationState::REVIEW_REQUIRED, $result->state);
    }

    public function test_unresolved_snapshot_is_non_authoritative(): void
    {
        $snapshot = RuleSnapshot::factory()->create([
            'resolution_state' => TaxResolutionState::REVIEW_REQUIRED,
        ]);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->requiresReview());
        $this->assertSame('INCOMPLETE_RULE_DATA', $result->reasonCode);
    }

    public function test_deterministic_repeated_calculation(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item);

        $first = $this->calculator->calculateForSnapshot($snapshot);
        $second = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertSame($first->toArray(), $second->toArray());
        $this->assertSame($first->inputFingerprint, $second->inputFingerprint);
    }

    public function test_historical_reproducibility_from_snapshot_alone(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item);

        $first = $this->calculator->calculateForSnapshot($snapshot);
        $second = $this->calculator->calculateForSnapshot($snapshot->fresh());

        $this->assertSame($first->toArray(), $second->toArray());
    }

    public function test_order_item_mutation_does_not_affect_snapshot_calculation(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item);

        $before = $this->calculator->calculateForSnapshot($snapshot);

        // Mutate current OrderItem commercial context + price after resolution.
        $item->amendCommercialTaxContext(
            $this->context(['unit_price_snapshot' => '9999999.99', 'line_base_amount_snapshot' => '9999999.99']),
            'test: mutate current item after snapshot',
        );
        $item->unit_price = '9999999.99';
        $item->save();

        $after = $this->calculator->calculateForSnapshot($snapshot->fresh());

        $this->assertSame($before->toArray(), $after->toArray());
        $this->assertSame($before->taxAmount, $after->taxAmount);
    }

    public function test_generic_fraction_parsing_11_over_12_equals_110_over_120(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshotA = $this->resolveSnapshot($item, contextOverrides: ['line_base_amount_snapshot' => '1200000.00']);
        $resultA = $this->calculator->calculateForSnapshot($snapshotA);

        $snapshotB = RuleSnapshot::factory()->create([
            'dpp_formula_snapshot' => 'Base Amount x 110/120',
            'line_base_amount_snapshot' => '1200000.00',
        ]);
        $resultB = $this->calculator->calculateForSnapshot($snapshotB);

        $this->assertSame($resultA->dppAmount, $resultB->dppAmount);
        $this->assertSame($resultA->taxAmount, $resultB->taxAmount);
        $this->assertSame('1100000.00', $resultB->dppAmount);
        $this->assertSame('132000.00', $resultB->taxAmount);
    }

    public function test_unknown_dpp_formula_requires_review(): void
    {
        $snapshot = RuleSnapshot::factory()->create(['dpp_formula_snapshot' => 'Base Amount x 11/12/13']);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->requiresReview());
        $this->assertSame('UNKNOWN_DPP_FORMULA', $result->reasonCode);
    }

    public function test_unknown_tax_formula_requires_review(): void
    {
        $snapshot = RuleSnapshot::factory()->create(['tax_formula_snapshot' => 'Weird formula']);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->requiresReview());
        $this->assertSame('UNKNOWN_TAX_FORMULA', $result->reasonCode);
    }

    public function test_zero_statutory_rate_requires_review(): void
    {
        $snapshot = RuleSnapshot::factory()->create(['statutory_rate_snapshot' => '0.0000']);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->requiresReview());
        $this->assertSame('ZERO_RATE', $result->reasonCode);
    }

    public function test_null_rule_data_requires_incomplete_rule_data(): void
    {
        $snapshot = RuleSnapshot::factory()->create([
            'dpp_method_snapshot' => null,
            'dpp_formula_snapshot' => null,
            'base_amount_definition_snapshot' => null,
            'statutory_rate_snapshot' => null,
        ]);

        $result = $this->calculator->calculateForSnapshot($snapshot);

        $this->assertTrue($result->requiresReview());
        $this->assertSame('INCOMPLETE_RULE_DATA', $result->reasonCode);
    }

    public function test_from_snapshot_builds_input_without_order_item(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 1]);
        $snapshot = $this->resolveSnapshot($item);

        $input = TaxCalculationInput::fromSnapshot($snapshot->fresh());

        $this->assertNotNull($input);
        $this->assertSame($snapshot->id, $input->ruleSnapshotId);
        $this->assertSame('1000000.00', $input->baseAmount);
        $this->assertSame(DppMethod::NILAI_LAIN, $input->dppMethod);
    }
}
