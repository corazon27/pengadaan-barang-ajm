<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\VatCollectorStatus;
use App\Services\AuditLogger;
use App\Values\CommercialTaxContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * Commercial tax context columns frozen at order time. Once
     * commercial_context_frozen_at is set, direct mutation of any of these
     * columns throws unless performed through the explicit amendment path.
     *
     * @var list<string>
     */
    private const CONTEXT_COLUMNS = [
        'unit_price_snapshot',
        'line_base_amount_snapshot',
        'product_classification_snapshot',
        'buyer_classification_snapshot',
        'collector_status_snapshot',
        'transaction_type_snapshot',
        'taxpayer_status_snapshot',
        'order_time_rule_id',
        'order_time_rule_code',
        'order_time_rule_version',
    ];

    /**
     * Transient flag set only while amendCommercialTaxContext() is persisting.
     * Never stored; lets the freeze guard distinguish the explicit amendment
     * path from a silent mutation attempt.
     */
    public bool $allowingContextAmendment = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'product_sku_snapshot',
        'product_title_snapshot',
        'ppn_rate_snapshot',
        'pph_rate_snapshot',
        'unit_price_snapshot',
        'line_base_amount_snapshot',
        'product_classification_snapshot',
        'buyer_classification_snapshot',
        'collector_status_snapshot',
        'transaction_type_snapshot',
        'taxpayer_status_snapshot',
        'order_time_rule_id',
        'order_time_rule_code',
        'order_time_rule_version',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'ppn_rate_snapshot' => 'decimal:2',
            'pph_rate_snapshot' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'line_base_amount_snapshot' => 'decimal:2',
            'collector_status_snapshot' => VatCollectorStatus::class,
            'commercial_context_frozen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            $attributes = $item->getAttributes();

            if (! array_key_exists('unit_price', $attributes) || $attributes['unit_price'] === null) {
                $item->unit_price = (string) (Product::query()->whereKey($item->product_id)->value('base_price') ?? 0);
            }

            // Freeze the commercial identity if not explicitly provided (e.g. in
            // tests/factories). The controller always sets these at order time.
            if (empty($attributes['product_sku_snapshot']) || empty($attributes['product_title_snapshot'])) {
                $product = Product::query()->whereKey($item->product_id)->first(['sku', 'title', 'tax_rate_percentage', 'pph_rate_percentage']);

                if ($product) {
                    $item->product_sku_snapshot = $product->sku;
                    $item->product_title_snapshot = $product->title;
                }

                if ($item->getAttribute('ppn_rate_snapshot') === null) {
                    $item->ppn_rate_snapshot = $product?->tax_rate_percentage;
                }

                if ($item->getAttribute('pph_rate_snapshot') === null) {
                    $item->pph_rate_snapshot = $product?->pph_rate_percentage;
                }
            }

            // Freeze guard: once the commercial context is frozen, any direct
            // write to a context column is rejected unless the explicit
            // amendment path is active.
            if (
                $item->getOriginal('commercial_context_frozen_at') !== null
                && $item->isDirty(self::CONTEXT_COLUMNS)
                && ! $item->allowingContextAmendment
            ) {
                throw new LogicException('Commercial tax context is frozen; use amendCommercialTaxContext() to change it explicitly.');
            }

            $item->subtotal = bcmul((string) $item->unit_price, (string) $item->quantity, 2);
        });

        static::saved(function (OrderItem $item) {
            Order::query()->whereKey($item->order_id)->first()?->recalculateTotal();
        });

        static::deleted(function (OrderItem $item) {
            Order::query()->whereKey($item->order_id)->first()?->recalculateTotal();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ruleSnapshots(): HasMany
    {
        return $this->hasMany(RuleSnapshot::class);
    }

    /**
     * Freeze the order-time commercial tax context. One-time operation: throws
     * if the context is already frozen. Writes an immutable
     * ORDER_COMMERCIAL_CONTEXT_FROZEN audit row capturing the full original
     * context (who/when/what) as historical evidence.
     */
    public function freezeCommercialTaxContext(CommercialTaxContext $context, ?User $actor = null): void
    {
        if ($this->commercial_context_frozen_at !== null) {
            throw new LogicException('Commercial tax context is already frozen.');
        }

        foreach ($context->toArray() as $column => $value) {
            $this->{$column} = $value;
        }

        $this->commercial_context_frozen_at = now();

        $this->save();

        app(AuditLogger::class)->log(
            $actor,
            AuditAction::ORDER_COMMERCIAL_CONTEXT_FROZEN,
            $this,
            previousState: [],
            newState: $context->toArray(),
        );
    }

    /**
     * Explicit, audited amendment of a frozen commercial tax context. The
     * original order-time state is never destroyed: the previous live context
     * becomes previous_state and the amendment is recorded with its reason.
     * commercial_context_frozen_at is never touched by an amendment.
     */
    public function amendCommercialTaxContext(CommercialTaxContext $newContext, string $reason, ?User $actor = null): void
    {
        if ($this->commercial_context_frozen_at === null) {
            throw new LogicException('Commercial tax context must be frozen before it can be amended.');
        }

        $previous = $this->commercialTaxContext();

        $this->allowingContextAmendment = true;

        try {
            foreach ($newContext->toArray() as $column => $value) {
                $this->{$column} = $value;
            }

            $this->save();
        } finally {
            $this->allowingContextAmendment = false;
        }

        app(AuditLogger::class)->log(
            $actor,
            AuditAction::ORDER_COMMERCIAL_CONTEXT_AMENDED,
            $this,
            previousState: $previous?->toArray() ?? [],
            newState: [...$newContext->toArray(), 'reason' => $reason],
        );
    }

    /**
     * Current (possibly amended) commercial tax context, or null when the item
     * has never been frozen.
     */
    public function commercialTaxContext(): ?CommercialTaxContext
    {
        if ($this->commercial_context_frozen_at === null) {
            return null;
        }

        return CommercialTaxContext::fromArray($this->getAttributes());
    }

    /**
     * Deterministically reconstruct the ORIGINAL order-time context from the
     * immutable ORDER_COMMERCIAL_CONTEXT_FROZEN audit record. Returns null when
     * no frozen record exists. The original is never overwritten by amendments.
     */
    public function originalCommercialTaxContext(): ?CommercialTaxContext
    {
        $frozen = AuditLog::query()
            ->where('entity_type', class_basename(self::class))
            ->where('entity_id', $this->id)
            ->where('action', AuditAction::ORDER_COMMERCIAL_CONTEXT_FROZEN->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($frozen === null || ! is_array($frozen->new_state)) {
            return null;
        }

        return CommercialTaxContext::fromArray($frozen->new_state);
    }
}
