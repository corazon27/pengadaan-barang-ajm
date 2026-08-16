<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BuyerClassification;
use App\Enums\TaxApplicability;
use App\Enums\TaxResolutionState;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use App\Models\FakturCode;
use App\Models\Product;
use App\Models\TaxRule;
use App\Services\TaxRuleResolver;
use App\Values\TaxRuleResolutionQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaxRuleResolverTest extends TestCase
{
    use RefreshDatabase;

    private TaxRuleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        FakturCode::factory()->create(['code' => '01']);

        $this->resolver = new TaxRuleResolver;
    }

    private function resolveQuery(array $overrides = []): TaxRuleResolutionQuery
    {
        return new TaxRuleResolutionQuery(
            taxType: $overrides['tax_type'] ?? TaxType::PPN,
            effectiveDate: $overrides['effective_date'] ?? Carbon::parse('2025-03-01'),
            taxpayerStatus: $overrides['taxpayer_status'] ?? 'PKP',
            buyerClassification: $overrides['buyer_classification'] ?? null,
            vatCollectorStatus: $overrides['vat_collector_status'] ?? null,
            transactionType: $overrides['transaction_type'] ?? 'PENYERAHAN_BKP_JKP',
            productClassification: $overrides['product_classification'] ?? null,
        );
    }

    public function test_resolves_on_exact_effective_start_date(): void
    {
        $rule = TaxRule::factory()->create(['effective_from' => '2025-02-04', 'effective_until' => '2025-07-31']);

        $resolution = $this->resolver->resolve($this->resolveQuery(['effective_date' => Carbon::parse('2025-02-04')]));

        $this->assertTrue($resolution->isResolved());
        $this->assertSame($rule->id, $resolution->resolvedRule?->id);
    }

    public function test_resolves_on_exact_effective_end_date(): void
    {
        $rule = TaxRule::factory()->create(['effective_from' => '2025-02-04', 'effective_until' => '2025-07-31']);

        $resolution = $this->resolver->resolve($this->resolveQuery(['effective_date' => Carbon::parse('2025-07-31')]));

        $this->assertTrue($resolution->isResolved());
        $this->assertSame($rule->id, $resolution->resolvedRule?->id);
    }

    public function test_resolves_open_ended_rule(): void
    {
        $rule = TaxRule::factory()->create(['effective_from' => '2025-08-01', 'effective_until' => null]);

        $resolution = $this->resolver->resolve($this->resolveQuery(['effective_date' => Carbon::parse('2026-08-15')]));

        $this->assertTrue($resolution->isResolved());
        $this->assertSame($rule->id, $resolution->resolvedRule?->id);
    }

    public function test_no_matching_rule_requires_review(): void
    {
        TaxRule::factory()->create();

        $resolution = $this->resolver->resolve($this->resolveQuery(['tax_type' => TaxType::PPH]));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertNull($resolution->resolvedRule);
        $this->assertSame('NO_MATCHING_RULE', $resolution->reasonCode);
        $this->assertTrue($resolution->requiresReview());
    }

    public function test_out_of_window_rule_is_no_match(): void
    {
        TaxRule::factory()->create(['effective_from' => '2025-02-04', 'effective_until' => '2025-07-31']);

        $resolution = $this->resolver->resolve($this->resolveQuery(['effective_date' => Carbon::parse('2025-08-15')]));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertSame('NO_MATCHING_RULE', $resolution->reasonCode);
    }

    public function test_review_required_rule_requires_review(): void
    {
        $rule = TaxRule::factory()->create([
            'applicability' => TaxApplicability::REVIEW_REQUIRED,
            'statutory_rate' => null,
            'effective_burden' => null,
        ]);

        $resolution = $this->resolver->resolve($this->resolveQuery());

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertSame('APPLICABILITY_REVIEW_REQUIRED', $resolution->reasonCode);
        $this->assertNull($resolution->resolvedRule);
        $this->assertTrue($resolution->conflictingRules->contains('id', $rule->id));
    }

    public function test_unresolved_applicability_requires_review(): void
    {
        TaxRule::factory()->create([
            'applicability' => TaxApplicability::UNRESOLVED,
            'statutory_rate' => null,
            'effective_burden' => null,
        ]);

        $resolution = $this->resolver->resolve($this->resolveQuery());

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertSame('APPLICABILITY_REVIEW_REQUIRED', $resolution->reasonCode);
    }

    public function test_max_tier_with_mixed_confirmed_and_review_required_requires_review(): void
    {
        $confirmed = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-09',
            'buyer_classification' => BuyerClassification::REGULAR,
            'applicability' => TaxApplicability::CONFIRMED,
        ]);
        $review = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-10',
            'buyer_classification' => BuyerClassification::REGULAR,
            'applicability' => TaxApplicability::REVIEW_REQUIRED,
            'statutory_rate' => null,
            'effective_burden' => null,
        ]);

        $resolution = $this->resolver->resolve($this->resolveQuery(['buyer_classification' => BuyerClassification::REGULAR]));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertNull($resolution->resolvedRule);
        $this->assertSame(2, $resolution->candidateCount);
        $this->assertTrue($resolution->conflictingRules->contains('id', $confirmed->id));
        $this->assertTrue($resolution->conflictingRules->contains('id', $review->id));
    }

    public function test_multiple_equal_specificity_confirmed_rules_conflict(): void
    {
        $a = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-05',
            'buyer_classification' => BuyerClassification::REGULAR,
        ]);
        $b = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-06',
            'buyer_classification' => BuyerClassification::REGULAR,
        ]);

        $resolution = $this->resolver->resolve($this->resolveQuery(['buyer_classification' => BuyerClassification::REGULAR]));

        $this->assertSame(TaxResolutionState::RULE_CONFLICT, $resolution->state);
        $this->assertNull($resolution->resolvedRule);
        $this->assertSame(2, $resolution->candidateCount);
        $this->assertCount(2, $resolution->conflictingRules);
        $this->assertTrue($resolution->conflictingRules->contains('id', $a->id));
        $this->assertTrue($resolution->conflictingRules->contains('id', $b->id));
    }

    public function test_overlapping_same_rule_versions_conflict(): void
    {
        TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'effective_from' => '2025-02-04',
            'effective_until' => '2025-12-31',
        ]);
        TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v2',
            'effective_from' => '2025-03-01',
            'effective_until' => null,
        ]);

        $resolution = $this->resolver->resolve($this->resolveQuery(['effective_date' => Carbon::parse('2025-06-01')]));

        $this->assertSame(TaxResolutionState::RULE_CONFLICT, $resolution->state);
        $this->assertNull($resolution->resolvedRule);
    }

    public function test_specificity_precedence_prefers_more_constrained_confirmed_rule(): void
    {
        $generic = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-01',
            'buyer_classification' => null,
            'applicability' => TaxApplicability::CONFIRMED,
        ]);
        $specific = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-07',
            'buyer_classification' => BuyerClassification::GOVERNMENT,
            'applicability' => TaxApplicability::CONFIRMED,
        ]);

        $resolution = $this->resolver->resolve($this->resolveQuery([
            'buyer_classification' => BuyerClassification::GOVERNMENT,
        ]));

        $this->assertTrue($resolution->isResolved());
        $this->assertSame($specific->id, $resolution->resolvedRule?->id);
        $this->assertNotSame($generic->id, $resolution->resolvedRule?->id);
    }

    public function test_lower_specificity_review_rule_does_not_block_higher_specificity_confirmed_rule(): void
    {
        TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-08',
            'buyer_classification' => null,
            'applicability' => TaxApplicability::REVIEW_REQUIRED,
            'statutory_rate' => null,
            'effective_burden' => null,
        ]);
        $specific = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-09',
            'buyer_classification' => BuyerClassification::GOVERNMENT,
            'applicability' => TaxApplicability::CONFIRMED,
        ]);

        $resolution = $this->resolver->resolve($this->resolveQuery([
            'buyer_classification' => BuyerClassification::GOVERNMENT,
        ]));

        $this->assertTrue($resolution->isResolved());
        $this->assertSame($specific->id, $resolution->resolvedRule?->id);
    }

    public function test_buyer_classification_mismatch_is_no_match(): void
    {
        TaxRule::factory()->create(['buyer_classification' => BuyerClassification::GOVERNMENT]);

        $resolution = $this->resolver->resolve($this->resolveQuery(['buyer_classification' => BuyerClassification::REGULAR]));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertSame('NO_MATCHING_RULE', $resolution->reasonCode);
    }

    public function test_collector_status_mismatch_is_no_match(): void
    {
        TaxRule::factory()->create(['vat_collector_status' => VatCollectorStatus::VERIFIED]);

        $resolution = $this->resolver->resolve($this->resolveQuery(['vat_collector_status' => VatCollectorStatus::UNVERIFIED]));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertSame('NO_MATCHING_RULE', $resolution->reasonCode);
    }

    public function test_taxpayer_status_mismatch_is_no_match(): void
    {
        TaxRule::factory()->create(['taxpayer_status' => 'NON_PKP']);

        $resolution = $this->resolver->resolve($this->resolveQuery(['taxpayer_status' => 'PKP']));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertSame('NO_MATCHING_RULE', $resolution->reasonCode);
    }

    public function test_transaction_type_mismatch_is_no_match(): void
    {
        TaxRule::factory()->create(['transaction_type' => 'SELF_CONSUMPTION']);

        $resolution = $this->resolver->resolve($this->resolveQuery(['transaction_type' => 'PENYERAHAN_BKP_JKP']));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertSame('NO_MATCHING_RULE', $resolution->reasonCode);
    }

    public function test_product_classification_mismatch_is_no_match(): void
    {
        TaxRule::factory()->create(['product_classification' => 'DIBEBASKAN']);

        $resolution = $this->resolver->resolve($this->resolveQuery(['product_classification' => 'TERUTANG']));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
        $this->assertSame('NO_MATCHING_RULE', $resolution->reasonCode);
    }

    public function test_rule_constraint_with_null_query_dimension_is_no_match(): void
    {
        TaxRule::factory()->create(['buyer_classification' => BuyerClassification::GOVERNMENT]);

        $resolution = $this->resolver->resolve($this->resolveQuery(['buyer_classification' => null]));

        $this->assertSame(TaxResolutionState::REVIEW_REQUIRED, $resolution->state);
    }

    public function test_legacy_product_tax_rate_never_influences_resolution(): void
    {
        TaxRule::factory()->create(['statutory_rate' => '12.0000', 'effective_burden' => '11.0000']);
        Product::factory()->create(['tax_rate_percentage' => 99.50]);

        $resolution = $this->resolver->resolve($this->resolveQuery());

        $this->assertTrue($resolution->isResolved());
        $this->assertSame('12.0000', $resolution->resolvedRule?->statutory_rate);
        $this->assertSame('11.0000', $resolution->resolvedRule?->effective_burden);
        $this->assertNotSame('99.50', $resolution->resolvedRule?->statutory_rate);
    }

    public function test_resolution_is_deterministic_across_repeats(): void
    {
        TaxRule::factory()->create(['buyer_classification' => BuyerClassification::GOVERNMENT]);

        $first = $this->resolver->resolve($this->resolveQuery(['buyer_classification' => BuyerClassification::GOVERNMENT]));
        $second = $this->resolver->resolve($this->resolveQuery(['buyer_classification' => BuyerClassification::GOVERNMENT]));

        $this->assertSame($first->state, $second->state);
        $this->assertSame($first->resolvedRule?->id, $second->resolvedRule?->id);
    }

    public function test_specificity_counts_only_transactional_dimensions_not_metadata(): void
    {
        $generic = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-01',
            'taxpayer_status' => 'PKP',
            'buyer_classification' => null,
        ]);
        $metadataHeavy = TaxRule::factory()->create([
            'rule_code' => 'TAX-PPN-11',
            'taxpayer_status' => 'PKP',
            'buyer_classification' => null,
            'legal_reference' => 'PMK 11/2025 jo PMK 53/2025; PMK 131/2024; extended citation',
            'source_version' => 'official',
            'base_amount_definition' => 'HARGA_JUAL',
            'dpp_formula' => 'Base Amount x 11/12',
            'tax_formula' => 'DPP x Statutory Rate',
        ]);

        $resolution = $this->resolver->resolve($this->resolveQuery());

        $this->assertSame(TaxResolutionState::RULE_CONFLICT, $resolution->state);
        $this->assertSame(2, $resolution->candidateCount);
        $this->assertTrue($resolution->conflictingRules->contains('id', $generic->id));
        $this->assertTrue($resolution->conflictingRules->contains('id', $metadataHeavy->id));
    }
}
