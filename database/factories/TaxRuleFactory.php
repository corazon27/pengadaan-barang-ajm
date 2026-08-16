<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DppMethod;
use App\Enums\TaxApplicability;
use App\Enums\TaxType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'taxpayer_status' => 'PKP',
            'verification_status' => VerificationStatus::UNVERIFIED,
            'buyer_classification' => null,
            'vat_collector_status' => null,
            'transaction_type' => 'PENYERAHAN_BKP_JKP',
            'product_classification' => null,
            'base_amount_definition' => 'HARGA_JUAL',
            'dpp_method' => DppMethod::NILAI_LAIN,
            'dpp_formula' => 'Base Amount x 11/12',
            'statutory_rate' => '12.0000',
            'tax_formula' => 'DPP x Statutory Rate',
            'effective_burden' => '11.0000',
            'faktur_code' => '01',
            'withholding_rule' => null,
            'legal_reference' => 'PMK 11/2025 jo PMK 53/2025; PMK 131/2024',
            'effective_from' => '2025-02-04',
            'effective_until' => '2025-07-31',
            'source_version' => 'official',
            'verification_date' => '2026-08-13',
            'applicability' => TaxApplicability::CONFIRMED,
        ];
    }
}
