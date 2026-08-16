<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BuyerClassification;
use App\Enums\DppMethod;
use App\Enums\TaxResolutionState;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

/**
 * Immutable authoritative record of a tax rule resolution per line item
 * (Phase 2C).
 *
 * Self-contained enough to reconstruct why a rule applied at the tax event
 * without mutating order/tax data. Once persisted it cannot be updated or
 * deleted: update()/save()/saveQuietly() on an existing row throws, and
 * delete() always throws. created_at is the immutable persist timestamp.
 *
 * The `dpp_amount` field is populated exactly once at creation time via
 * invoice integration and is thereafter immutable – representing a WRITE-ONCE
 * deferred calculation outcome. Resolution facts recorded in this model
 * are immutable and never mutated after creation.
 */
class RuleSnapshot extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_item_id',
        'tax_rule_id',
        'rule_code',
        'rule_version',
        'tax_type',
        'taxpayer_status',
        'buyer_classification',
        'vat_collector_status',
        'transaction_type',
        'product_classification',
        'resolution_date',
        'effective_from',
        'effective_until',
        'dpp_amount',
        'dpp_method_snapshot',
        'dpp_formula_snapshot',
        'base_amount_definition_snapshot',
        'unit_price_snapshot',
        'quantity_snapshot',
        'line_base_amount_snapshot',
        'statutory_rate_snapshot',
        'tax_formula_snapshot',
        'effective_burden_snapshot',
        'faktur_code',
        'withholding_snapshot',
        'legal_reference',
        'order_time_rule_id',
        'order_time_rule_code',
        'order_time_rule_version',
        'resolution_state',
    ];

    protected function casts(): array
    {
        return [
            'tax_type' => TaxType::class,
            'buyer_classification' => BuyerClassification::class,
            'vat_collector_status' => VatCollectorStatus::class,
            'resolution_state' => TaxResolutionState::class,
            'resolution_date' => 'date',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'dpp_amount' => 'decimal:2',
            'dpp_method_snapshot' => DppMethod::class,
            'unit_price_snapshot' => 'decimal:2',
            'quantity_snapshot' => 'integer',
            'line_base_amount_snapshot' => 'decimal:2',
            'statutory_rate_snapshot' => 'decimal:4',
            'effective_burden_snapshot' => 'decimal:4',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_rule_snapshots')
            ->withPivot('tax_amount', 'created_at')
            ->using(InvoiceRuleSnapshot::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('RuleSnapshot is immutable and cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new LogicException('RuleSnapshot is immutable and cannot be deleted.');
    }
}
