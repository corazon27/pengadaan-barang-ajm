<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Invoice ↔ RuleSnapshot junction (Phase 2C).
 *
 * One invoice carries many RuleSnapshot (per line item). This is the
 * relationship only — there is no second source-of-truth order-item link here;
 * canonical path is Invoice ↕ InvoiceRuleSnapshot ↕ RuleSnapshot ↓ OrderItem.
 * tax_amount stays NULL in Phase 2C (no tax math).
 */
class InvoiceRuleSnapshot extends Pivot
{
    use HasUuids;

    protected $table = 'invoice_rule_snapshots';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $casts = [
        'tax_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function ruleSnapshot(): BelongsTo
    {
        return $this->belongsTo(RuleSnapshot::class);
    }
}
