<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LegalFunctionApplicability;
use App\Enums\LegalFunctionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps a USER/PERSON to a legal function.
 *
 * CRITICAL INVARIANT: organizational role != legal function.
 * An RBAC role (e.g. SUPERADMIN) must NEVER be inferred as a statutory
 * function (e.g. DPO). This table is populated only from real appointment
 * data; no production seeding.
 *
 * human_review_case_id: current/originating review reference only.
 * Historical assessments reconstructible via HumanReviewCase records + audit_logs.
 */
class LegalFunctionAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'function_code',
        'function_category',
        'statutory_basis',
        'appointment_basis',
        'applicability_status',
        'effective_from',
        'effective_until',
        'scope',
        'status',
        'human_review_case_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => LegalFunctionStatus::class,
            'applicability_status' => LegalFunctionApplicability::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LegalFunctionAssignment $assignment) {
            $assignment->applicability_status ??= LegalFunctionApplicability::PENDING_LEGAL_REVIEW;
            $assignment->status ??= LegalFunctionStatus::INACTIVE;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function humanReviewCase()
    {
        return $this->belongsTo(HumanReviewCase::class, 'human_review_case_id');
    }

    public function isActive(): bool
    {
        return $this->status === LegalFunctionStatus::ACTIVE;
    }

    public function isApplicable(): bool
    {
        return $this->applicability_status === LegalFunctionApplicability::REQUIRED;
    }

    public function isExpired(): bool
    {
        return $this->effective_until !== null && $this->effective_until->isPast();
    }
}
