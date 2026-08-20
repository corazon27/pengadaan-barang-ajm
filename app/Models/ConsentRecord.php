<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentSourceChannel;
use App\Enums\ConsentStatus;
use App\Enums\LawfulBasis;
use App\Enums\SubjectType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'subject_user_id',
        'subject_type',
        'purpose',
        'processing_lawful_basis',
        'notice_version',
        'document_ref',
        'consent_status',
        'granted_at',
        'withdrawn_at',
        'withdrawal_deadline_at',
        'source_channel',
        'actor_user_id',
        'evidence_reference',
        'rule_id',
        'predecessor_consent_id',
    ];

    protected function casts(): array
    {
        return [
            'subject_type' => SubjectType::class,
            'processing_lawful_basis' => LawfulBasis::class,
            'consent_status' => ConsentStatus::class,
            'source_channel' => ConsentSourceChannel::class,
            'granted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'withdrawal_deadline_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ConsentRecord $record) {
            $record->consent_status ??= ConsentStatus::ACTIVE;
            $record->granted_at ??= now();
        });
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function predecessorConsent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_consent_id');
    }

    public function isExpired(): bool
    {
        return $this->consent_status === ConsentStatus::EXPIRED
            || (
                $this->consent_status === ConsentStatus::ACTIVE
                && $this->withdrawal_deadline_at !== null
                && $this->withdrawal_deadline_at->isPast()
            );
    }
}
