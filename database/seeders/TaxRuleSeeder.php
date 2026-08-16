<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BuyerClassification;
use App\Enums\DppMethod;
use App\Enums\TaxApplicability;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use App\Enums\VerificationStatus;
use App\Models\FakturCode;
use App\Models\TaxRule;
use Illuminate\Database\Seeder;

/**
 * Initial/reference tax rule data (Phase 2A).
 *
 * Source-cited against REGULATORY_RULEBOOK.md (verified 13-Aug-2026):
 *
 *   - TAX-PPN-01: PMK 11/2025 jo PMK 53/2025 (DPP nilai lain);
 *     PMK 131/2024 (PPN treatment — rate basis). PMK 11/2025 effective
 *     2025-02-04; PMK 53/2025 (amendment) effective 2025-08-01.
 *   - TAX-PPN-02: PER-11/PJ/2025 Lampiran D faktur code hierarchy
 *     (01 default / 02 government collectors only / 03 designated collectors
 *     only). Codes seeded as verified reference data only.
 *
 * Versioning (correction Q1): v1 covers 2025-02-04 → 2025-07-31. The PMK
 * 53/2025 amendment's effect on the AJM use case is NOT verified, so v2
 * (2025-08-01 → open) is seeded as REVIEW_REQUIRED with NO fabricated
 * formula/rate (statutory_rate, effective_burden, formulas all NULL).
 * taxpayer_status (value) and verification_status (state) are separate
 * fields (correction Q4).
 */
class TaxRuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFakturCodes();
        $this->seedTaxRules();
    }

    private function seedFakturCodes(): void
    {
        $codes = [
            [
                'code' => '01',
                'description' => 'Penyerahan BKP/JKP pada umumnya (kode default)',
                'required_buyer_class' => null,
                'required_collector_status' => null,
                'effective_from' => '2025-02-04',
                'effective_until' => null,
            ],
            [
                'code' => '02',
                'description' => 'Penyerahan kepada pemungut PPN instansi pemerintah',
                'required_buyer_class' => BuyerClassification::GOVERNMENT->value,
                'required_collector_status' => VatCollectorStatus::VERIFIED->value,
                'effective_from' => '2025-02-04',
                'effective_until' => null,
            ],
            [
                'code' => '03',
                'description' => 'Penyerahan kepada pemungut PPN yang ditunjuk (designated collector)',
                'required_buyer_class' => BuyerClassification::DESIGNATED_COLLECTOR->value,
                'required_collector_status' => VatCollectorStatus::VERIFIED->value,
                'effective_from' => '2025-02-04',
                'effective_until' => null,
            ],
        ];

        foreach ($codes as $code) {
            FakturCode::updateOrCreate(
                ['code' => $code['code']],
                $code
            );
        }
    }

    private function seedTaxRules(): void
    {
        // TAX-PPN-01 v1 — confirmed basis under PMK 11/2025 (2025-02-04 → 2025-07-31).
        TaxRule::updateOrCreate(
            ['rule_code' => 'TAX-PPN-01', 'rule_version' => 'v1'],
            [
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
                'legal_reference' => 'PMK 11/2025 jo PMK 53/2025 (DPP nilai lain); PMK 131/2024 (PPN treatment, rate basis); PER-11/PJ/2025 Lampiran D (faktur code)',
                'effective_from' => '2025-02-04',
                'effective_until' => '2025-07-31',
                'source_version' => 'official',
                'verification_date' => '2026-08-13',
                'applicability' => TaxApplicability::CONFIRMED,
            ]
        );

        // TAX-PPN-01 v2 — PMK 53/2025 amendment window (2025-08-01 → open).
        // Amendment effect on the AJM use case is UNVERIFIED: no rate/formula
        // is fabricated. REVIEW_REQUIRED blocks authoritative resolution.
        TaxRule::updateOrCreate(
            ['rule_code' => 'TAX-PPN-01', 'rule_version' => 'v2'],
            [
                'tax_type' => TaxType::PPN,
                'taxpayer_status' => 'PKP',
                'verification_status' => VerificationStatus::UNVERIFIED,
                'buyer_classification' => null,
                'vat_collector_status' => null,
                'transaction_type' => 'PENYERAHAN_BKP_JKP',
                'product_classification' => null,
                'base_amount_definition' => 'HARGA_JUAL',
                'dpp_method' => DppMethod::NILAI_LAIN,
                'dpp_formula' => null,
                'statutory_rate' => null,
                'tax_formula' => null,
                'effective_burden' => null,
                'faktur_code' => null,
                'withholding_rule' => null,
                'legal_reference' => 'PMK 11/2025 jo PMK 53/2025 (amendment effect for AJM use case UNVERIFIED)',
                'effective_from' => '2025-08-01',
                'effective_until' => null,
                'source_version' => 'official',
                'verification_date' => null,
                'applicability' => TaxApplicability::REVIEW_REQUIRED,
            ]
        );
    }
}
