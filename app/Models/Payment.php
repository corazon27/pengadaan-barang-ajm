<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'amount',
        'payment_method',
        'payment_date',
        'proof_file_url',
        'notes',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'payment_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->status ??= PaymentStatus::PENDING_VERIFICATION;
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function statusLabel(): string
    {
        return $this->status->statusLabel();
    }

    public function paymentMethodLabel(): string
    {
        return $this->payment_method->label();
    }

    /**
     * Restrict the query to payments the given user may see.
     *
     * This is query scoping only — it is NOT authorisation. Controllers must
     * still perform an explicit Policy check. Ownership is derived through the
     * invoice → order chain (the payment's user_id is the uploader, not the
     * authoritative owner). SUPERADMIN sees every record (matching the policies).
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->role === UserRole::SUPERADMIN
            ? $query
            : $query->whereHas('invoice', fn (Builder $invoice) => $invoice->whereHas(
                'order',
                fn (Builder $order) => $order->where('user_id', $user->id),
            ));
    }
}
