<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgreementStatus;
use App\Enums\ProcessorRoleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Processor/counterparty governance under PDP-PROC-001.
 *
 * Two independent state machines:
 * - processor_role_status: UNKNOWN / REQUIRE_REVIEW / PROCESSOR / NOT_PROCESSOR
 * - agreement_status: DRAFT / ACTIVE / SUSPENDED / EXPIRED / TERMINATED
 *
 * agreement_status = persisted lifecycle state.
 * isActiveAgreement() = derived temporal effectiveness (checks effective_until).
 * agreement_status = ACTIVE AND isActiveAgreement() = false IS VALID when
 * effective_until is in the past.
 */
class ProcessorAgreement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'counterparty_name',
        'counterparty_identifier',
        'processor_role_status',
        'agreement_status',
        'data_categories',
        'processing_purposes',
        'legal_basis',
        'evidence_reference',
        'evidence_verified',
        'evidence_verified_at',
        'effective_from',
        'effective_until',
        'human_review_case_id',
        'actor_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'processor_role_status' => ProcessorRoleStatus::class,
            'agreement_status' => AgreementStatus::class,
            'counterparty_identifier' => 'array',
            'data_categories' => 'array',
            'processing_purposes' => 'array',
            'evidence_verified' => 'boolean',
            'evidence_verified_at' => 'datetime',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProcessorAgreement $agreement) {
            $agreement->processor_role_status ??= ProcessorRoleStatus::UNKNOWN;
            $agreement->agreement_status ??= AgreementStatus::DRAFT;
            $agreement->evidence_verified ??= false;
        });
    }

    /**
     * Derived temporal effectiveness. Returns true when agreement_status is
     * ACTIVE and effective_until has not passed (or is null).
     *
     * agreement_status = ACTIVE AND isActiveAgreement() = false is valid
     * when effective_until is in the past.
     */
    public function isActiveAgreement(): bool
    {
        if ($this->agreement_status !== AgreementStatus::ACTIVE) {
            return false;
        }

        if ($this->effective_until !== null && $this->effective_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Whether a processor role has been determined (deterministic or review).
     */
    public function isRoleDetermined(): bool
    {
        return in_array($this->processor_role_status, [
            ProcessorRoleStatus::PROCESSOR,
            ProcessorRoleStatus::NOT_PROCESSOR,
        ]);
    }

    /**
     * Whether the processor role requires human review.
     */
    public function isReviewRequired(): bool
    {
        return $this->processor_role_status === ProcessorRoleStatus::REQUIRE_REVIEW;
    }

    public function humanReviewCase()
    {
        return $this->belongsTo(HumanReviewCase::class, 'human_review_case_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
