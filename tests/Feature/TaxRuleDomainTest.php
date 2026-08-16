<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BuyerClassification;
use App\Enums\DppMethod;
use App\Enums\TaxApplicability;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use App\Enums\VerificationStatus;
use App\Models\FakturCode;
use App\Models\TaxRule;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TaxRuleDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_rule_defaults_to_unresolved_applicability(): void
    {
        $rule = TaxRule::create([
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'dpp_method' => DppMethod::NILAI_LAIN,
            'legal_reference' => 'PMK 11/2025',
            'effective_from' => '2025-02-04',
        ]);

        $this->assertSame(TaxApplicability::UNRESOLVED, $rule->applicability);
        $this->assertSame(TaxType::PPN, $rule->tax_type);
        $this->assertSame(DppMethod::NILAI_LAIN, $rule->dpp_method);
    }

    public function test_tax_rule_code_and_version_are_unique(): void
    {
        TaxRule::create([
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'dpp_method' => DppMethod::NILAI_LAIN,
            'legal_reference' => 'PMK 11/2025',
            'effective_from' => '2025-02-04',
        ]);

        $this->expectException(QueryException::class);

        TaxRule::create([
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'dpp_method' => DppMethod::NILAI_LAIN,
            'legal_reference' => 'PMK 11/2025',
            'effective_from' => '2025-02-04',
        ]);
    }

    public function test_tax_rule_requires_legal_reference(): void
    {
        $this->expectException(QueryException::class);

        TaxRule::create([
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'dpp_method' => DppMethod::NILAI_LAIN,
            'effective_from' => '2025-02-04',
        ]);
    }

    public function test_tax_rule_rejects_invalid_effective_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('effective_until');

        TaxRule::create([
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'dpp_method' => DppMethod::NILAI_LAIN,
            'legal_reference' => 'PMK 11/2025',
            'effective_from' => '2025-08-01',
            'effective_until' => '2025-07-31',
        ]);
    }

    public function test_lainnya_dpp_method_requires_review_until_source_cited(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('LAINNYA');

        TaxRule::create([
            'rule_code' => 'TAX-PPN-99',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'dpp_method' => DppMethod::LAINNYA,
            'legal_reference' => 'uncited',
            'effective_from' => '2025-02-04',
            'applicability' => TaxApplicability::CONFIRMED,
        ]);
    }

    public function test_lainnya_dpp_method_allowed_when_review_required(): void
    {
        $rule = TaxRule::create([
            'rule_code' => 'TAX-PPN-99',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'dpp_method' => DppMethod::LAINNYA,
            'legal_reference' => 'uncited',
            'effective_from' => '2025-02-04',
            'applicability' => TaxApplicability::REVIEW_REQUIRED,
        ]);

        $this->assertSame(TaxApplicability::REVIEW_REQUIRED, $rule->applicability);
    }

    public function test_tax_rule_is_effective_on_inclusive_bounds(): void
    {
        $rule = TaxRule::create([
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'dpp_method' => DppMethod::NILAI_LAIN,
            'legal_reference' => 'PMK 11/2025',
            'effective_from' => '2025-02-04',
            'effective_until' => '2025-07-31',
        ]);

        $this->assertFalse($rule->isEffectiveOn(now()->parse('2025-02-03')));
        $this->assertTrue($rule->isEffectiveOn(now()->parse('2025-02-04')));
        $this->assertTrue($rule->isEffectiveOn(now()->parse('2025-07-31')));
        $this->assertFalse($rule->isEffectiveOn(now()->parse('2025-08-01')));
    }

    public function test_tax_rule_requires_existing_faktur_code(): void
    {
        FakturCode::factory()->create(['code' => '01']);

        $this->expectException(QueryException::class);

        TaxRule::create([
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'dpp_method' => DppMethod::NILAI_LAIN,
            'legal_reference' => 'PMK 11/2025',
            'effective_from' => '2025-02-04',
            'faktur_code' => '99',
        ]);
    }

    public function test_faktur_code_hierarchy_restrictions_seeded_as_reference_data(): void
    {
        (new TaxRuleSeeder)->run();

        $default = FakturCode::where('code', '01')->firstOrFail();
        $government = FakturCode::where('code', '02')->firstOrFail();
        $designated = FakturCode::where('code', '03')->firstOrFail();

        $this->assertNull($default->required_buyer_class);
        $this->assertNull($default->required_collector_status);

        $this->assertSame(BuyerClassification::GOVERNMENT->value, $government->required_buyer_class);
        $this->assertSame(VatCollectorStatus::VERIFIED->value, $government->required_collector_status?->value);

        $this->assertSame(BuyerClassification::DESIGNATED_COLLECTOR->value, $designated->required_buyer_class);
        $this->assertSame(VatCollectorStatus::VERIFIED->value, $designated->required_collector_status?->value);
    }

    public function test_seeder_creates_versioned_rules_with_effective_dates(): void
    {
        (new TaxRuleSeeder)->run();

        $v1 = TaxRule::where('rule_code', 'TAX-PPN-01')->where('rule_version', 'v1')->firstOrFail();
        $v2 = TaxRule::where('rule_code', 'TAX-PPN-01')->where('rule_version', 'v2')->firstOrFail();

        $this->assertSame('2025-02-04', $v1->effective_from->toDateString());
        $this->assertSame('2025-07-31', $v1->effective_until->toDateString());
        $this->assertSame(TaxApplicability::CONFIRMED, $v1->applicability);

        $this->assertSame('2025-08-01', $v2->effective_from->toDateString());
        $this->assertNull($v2->effective_until);
        $this->assertSame(TaxApplicability::REVIEW_REQUIRED, $v2->applicability);
    }

    public function test_seeder_does_not_fabricate_rates_for_unverified_amendment(): void
    {
        (new TaxRuleSeeder)->run();

        $v1 = TaxRule::where('rule_code', 'TAX-PPN-01')->where('rule_version', 'v1')->firstOrFail();
        $v2 = TaxRule::where('rule_code', 'TAX-PPN-01')->where('rule_version', 'v2')->firstOrFail();

        $this->assertSame('12.0000', $v1->statutory_rate);
        $this->assertSame('11.0000', $v1->effective_burden);
        $this->assertSame('01', $v1->faktur_code);

        $this->assertNull($v2->statutory_rate);
        $this->assertNull($v2->effective_burden);
        $this->assertNull($v2->dpp_formula);
        $this->assertNull($v2->tax_formula);
        $this->assertNull($v2->faktur_code);
    }

    public function test_seeder_is_idempotent(): void
    {
        (new TaxRuleSeeder)->run();
        (new TaxRuleSeeder)->run();

        $this->assertSame(3, FakturCode::count());
        $this->assertSame(2, TaxRule::count());
    }

    public function test_taxpayer_status_and_verification_status_are_separate_fields(): void
    {
        (new TaxRuleSeeder)->run();

        $v1 = TaxRule::where('rule_code', 'TAX-PPN-01')->where('rule_version', 'v1')->firstOrFail();

        $this->assertSame('PKP', $v1->taxpayer_status);
        $this->assertSame(VerificationStatus::UNVERIFIED, $v1->verification_status);
    }

    public function test_rates_are_data_not_code_constants(): void
    {
        (new TaxRuleSeeder)->run();

        $v1 = TaxRule::where('rule_code', 'TAX-PPN-01')->where('rule_version', 'v1')->firstOrFail();

        // The 11/12 factor lives in DPP determination; effective burden is 11%
        // of Harga Jual. Both must be persisted as rule data for the future
        // TaxCalculationService to evaluate — never hardcoded in code.
        $this->assertStringContainsString('11/12', $v1->dpp_formula);
        $this->assertSame('12.0000', $v1->statutory_rate);
    }
}
