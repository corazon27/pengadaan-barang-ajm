<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BuyerClassification;
use App\Enums\DppMethod;
use App\Enums\TaxResolutionState;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use App\Models\OrderItem;
use App\Models\RuleSnapshot;
use App\Models\TaxRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleSnapshot>
 */
class RuleSnapshotFactory extends Factory
{
    protected $model = RuleSnapshot::class;

    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'tax_rule_id' => fn (array $attributes) => TaxRule::factory()
                ->create(['faktur_code' => '01'])
                ->id,
            'rule_code' => 'TAX-PPN-01',
            'rule_version' => 'v1',
            'tax_type' => TaxType::PPN,
            'taxpayer_status' => 'PKP',
            'buyer_classification' => BuyerClassification::REGULAR,
            'vat_collector_status' => VatCollectorStatus::NOT_APPLICABLE,
            'transaction_type' => 'PENYERAHAN_BKP_JKP',
            'product_classification' => null,
            'resolution_date' => '2025-03-01',
            'effective_from' => '2025-02-04',
            'effective_until' => '2025-07-31',
            'dpp_amount' => null,
            'dpp_method_snapshot' => DppMethod::NILAI_LAIN,
            'dpp_formula_snapshot' => 'Base Amount x 11/12',
            'base_amount_definition_snapshot' => 'HARGA_JUAL',
            'unit_price_snapshot' => '1000000.00',
            'quantity_snapshot' => 1,
            'line_base_amount_snapshot' => '1000000.00',
            'statutory_rate_snapshot' => '12.0000',
            'tax_formula_snapshot' => 'DPP x Statutory Rate',
            'effective_burden_snapshot' => '11.0000',
            'faktur_code' => '01',
            'withholding_snapshot' => null,
            'legal_reference' => 'PMK 11/2025 jo PMK 53/2025',
            'order_time_rule_id' => null,
            'order_time_rule_code' => null,
            'order_time_rule_version' => null,
            'resolution_state' => TaxResolutionState::RESOLVED,
        ];
    }
}
