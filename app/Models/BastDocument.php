<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BastStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BastDocument extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'bast_number',
        'status',
        'bast_document_url',
        'signed_by',
        'signed_at',
        'signed_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => BastStatus::class,
            'signed_at' => 'datetime',
            'signed_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            BastStatus::PENDING_SIGNATURE => 'Menunggu Tanda Tangan',
            BastStatus::SIGNED => 'Ditandatangani',
            default => $this->status->value,
        };
    }
}
