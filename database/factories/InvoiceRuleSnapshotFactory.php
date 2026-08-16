<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceRuleSnapshot;
use App\Models\RuleSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceRuleSnapshot>
 */
class InvoiceRuleSnapshotFactory extends Factory
{
    protected $model = InvoiceRuleSnapshot::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'rule_snapshot_id' => RuleSnapshot::factory(),
            'tax_amount' => null,
        ];
    }
}
