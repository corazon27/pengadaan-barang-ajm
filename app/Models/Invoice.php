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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'tax_calculation_version',
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
            'tax_calculation_version' => 'string',
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
     * Rule snapshots attached to this invoice (per line item), joined through
     * the invoice_rule_snapshots junction. No tax math in Phase 2C.
     */
    public function ruleSnapshots(): BelongsToMany
    {
        return $this->belongsToMany(RuleSnapshot::class, 'invoice_rule_snapshots')
            ->withPivot('tax_amount', 'created_at')
            ->using(InvoiceRuleSnapshot::class);
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

    /**
     * Allowed status transitions for the invoice payment state machine (INT-6).
     * Self-transitions are permitted so idempotent updates do not fail.
     *
     * @return list<string>
     */
    public static function allowedTransitionsFrom(InvoiceStatus $from): array
    {
        return match ($from) {
            InvoiceStatus::UNPAID => [
                InvoiceStatus::UNPAID->value,
                InvoiceStatus::PARTIALLY_PAID->value,
                InvoiceStatus::OVERDUE->value,
                InvoiceStatus::PAID->value,
            ],
            InvoiceStatus::PARTIALLY_PAID => [
                InvoiceStatus::PARTIALLY_PAID->value,
                InvoiceStatus::PAID->value,
                InvoiceStatus::OVERDUE->value,
            ],
            InvoiceStatus::OVERDUE => [
                InvoiceStatus::OVERDUE->value,
                InvoiceStatus::PARTIALLY_PAID->value,
                InvoiceStatus::PAID->value,
            ],
            InvoiceStatus::PAID => [
                InvoiceStatus::PAID->value,
            ],
            // REVIEW_REQUIRED is an intermediate computation state: tax is not
            // yet computed. No manual status change may move it forward — only
            // the recalculate-tax flow resolves it back to UNPAID.
            InvoiceStatus::REVIEW_REQUIRED => [],
        };
    }

    public function canTransitionTo(InvoiceStatus $to): bool
    {
        return in_array($to->value, self::allowedTransitionsFrom($this->status), true);
    }
}
