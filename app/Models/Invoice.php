<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'issued_date',
        'due_date',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'amount_due' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bastDocument(): BelongsTo
    {
        return $this->belongsTo(BastDocument::class);
    }
}
