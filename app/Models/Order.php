<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_number',
        'user_id',
        'rfq_id',
        'status',
        'top_days',
        'po_document_url',
        'lkpp_product_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'top_days' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            $order->total_amount = self::sumItemSubtotals($order);
        });
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = self::sumItemSubtotals($this);
        $this->saveQuietly();
    }

    /**
     * Sum the item subtotals using decimal math so the order total never drifts
     * due to float rounding (INT-5).
     *
     * @return string amount formatted to 2 decimal places
     */
    private static function sumItemSubtotals(Order $order): string
    {
        return $order->items()->get()->reduce(
            fn (string $carry, OrderItem $item) => bcadd($carry, (string) $item->subtotal, 2),
            '0.00'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function bastDocument(): HasOne
    {
        return $this->hasOne(BastDocument::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function statusLabel(): string
    {
        return $this->status->statusLabel();
    }

    /**
     * Restrict the query to records the given user may see.
     *
     * This is query scoping only — it is NOT authorisation. Controllers must
     * still perform an explicit Policy check. SUPERADMIN sees every record
     * (matching the policies); buyers are limited to their own records.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->role === UserRole::SUPERADMIN
            ? $query
            : $query->where('user_id', $user->id);
    }
}
