<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DsrChannel;
use App\Enums\DsrStatus;
use App\Enums\SubjectType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSubjectRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'subject_user_id',
        'subject_type',
        'right_code',
        'channel',
        'request_input',
        'identity_verification_status',
        'identity_confidence',
        'identity_verification_meta',
        'processing_lawful_basis_evaluated',
        'status',
        'applicability_status',
        'handled_by',
        'human_review_case_id',
        'decision_notes',
        'internal_sla_target_at',
    ];

    protected function casts(): array
    {
        return [
            'subject_type' => SubjectType::class,
            'channel' => DsrChannel::class,
            'identity_verification_status' => VerificationStatus::class,
            'status' => DsrStatus::class,
            'request_input' => 'array',
            'identity_verification_meta' => 'array',
            'processing_lawful_basis_evaluated' => 'array',
            'internal_sla_target_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DataSubjectRequest $dsr) {
            $dsr->status ??= DsrStatus::RECEIVED;
            $dsr->identity_verification_status ??= VerificationStatus::UNVERIFIED;
            $dsr->applicability_status ??= 'APPLICABILITY_UNKNOWN';
        });
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function humanReviewCase(): BelongsTo
    {
        return $this->belongsTo(HumanReviewCase::class, 'human_review_case_id');
    }
}
