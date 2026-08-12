<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RfqStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rfq extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'rfq_number',
        'user_id',
        'status',
        'quotation_pdf_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfqStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }
}
