<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RfqStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
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
        'quotation_pdf_url',
        'notes',
        'valid_until',
        'admin_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfqStatus::class,
            'valid_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Rfq $rfq) {
            $rfq->status ??= RfqStatus::SUBMITTED;
        });
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

    /**
     * Restrict the query to records the given user may see.
     *
     * This is query scoping only — it is NOT authorisation. Controllers must
     * still perform an explicit Policy check. SUPERADMIN sees every record
     * (matching the policies); buyers are limited to their own records.
     *
     * @param  Builder<Rfq>  $query
     * @return Builder<Rfq>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->role === UserRole::SUPERADMIN
            ? $query
            : $query->where('user_id', $user->id);
    }
}
