<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerm;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'bast_id',
        'invoice_number',
        'faktur_pajak_number',
        'invoice_pdf_url',
        'faktur_pajak_url',
        'amount_due',
        'subtotal',
        'ppn_amount',
        'pph_amount',
        'payment_term',
        'grand_total',
        'issued_date',
        'due_date',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'payment_term' => PaymentTerm::class,
            'issued_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'amount_due' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'ppn_amount' => 'decimal:2',
            'pph_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->status ??= InvoiceStatus::UNPAID;
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bastDocument(): BelongsTo
    {
        return $this->belongsTo(BastDocument::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Total of verified payment amounts for this invoice, using BC Math.
     */
    public function verifiedPaidAmount(): string
    {
        return $this->payments()
            ->where('status', PaymentStatus::VERIFIED)
            ->get()
            ->reduce(
                fn (string $carry, Payment $payment) => bcadd($carry, (string) $payment->amount, 2),
                '0.00'
            );
    }
}
