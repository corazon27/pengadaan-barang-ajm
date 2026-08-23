<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\HumanReviewStatus;
use App\Enums\HumanReviewType;
use App\Enums\LegalFunctionApplicability;
use App\Enums\LegalFunctionStatus;
use App\Models\HumanReviewCase;
use App\Models\LegalFunctionAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class LegalFunctionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Create a new legal function assignment.
     * Defaults: INACTIVE + PENDING_LEGAL_REVIEW.
     */
    public function create(
        User $actor,
        array $data,
    ): LegalFunctionAssignment {
        return DB::transaction(function () use ($actor, $data) {
            $assignment = LegalFunctionAssignment::create([
                'user_id' => $data['user_id'] ?? null,
                'function_code' => $data['function_code'],
                'function_category' => $data['function_category'],
                'statutory_basis' => $data['statutory_basis'] ?? null,
                'appointment_basis' => $data['appointment_basis'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_until' => $data['effective_until'] ?? null,
                'scope' => $data['scope'] ?? null,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::LEGAL_FUNCTION_CREATED,
                $assignment,
                [],
                $this->auditSnapshot($assignment),
            );

            return $assignment;
        });
    }

    /**
     * Update a legal function assignment with lifecycle-aware validation.
     *
     * C20/C22:
     * - TERMINATED: no updates allowed
     * - ACTIVE/SUSPENDED: function_code immutable; user_id cannot be null
     * - INACTIVE: all accepted fields mutable
     * - status and applicability_status never accepted (managed by dedicated flows)
     */
    public function update(
        User $actor,
        LegalFunctionAssignment $assignment,
        array $data,
    ): LegalFunctionAssignment {
        if ($assignment->status === LegalFunctionStatus::TERMINATED) {
            throw new \InvalidArgumentException('Cannot update a TERMINATED legal function assignment.');
        }

        if (in_array($assignment->status, [LegalFunctionStatus::ACTIVE, LegalFunctionStatus::SUSPENDED])) {
            if (array_key_exists('function_code', $data) && $data['function_code'] !== $assignment->function_code) {
                throw new \InvalidArgumentException('Cannot change function_code on an ACTIVE or SUSPENDED legal function.');
            }

            if (array_key_exists('user_id', $data) && $data['user_id'] === null) {
                throw new \InvalidArgumentException('Cannot set user_id to null on an ACTIVE or SUSPENDED legal function.');
            }
        }

        return DB::transaction(function () use ($actor, $assignment, $data) {
            $previousState = $this->auditSnapshot($assignment);

            $allowed = [
                'user_id', 'function_code', 'function_category', 'statutory_basis',
                'appointment_basis', 'effective_from', 'effective_until', 'scope',
            ];

            $filtered = array_intersect_key($data, array_flip($allowed));

            $assignment->update($filtered);

            $this->auditLogger->log(
                $actor,
                AuditAction::LEGAL_FUNCTION_UPDATED,
                $assignment,
                $previousState,
                $this->auditSnapshot($assignment),
            );

            return $assignment->refresh();
        });
    }

    /**
     * Activate a legal function assignment.
     *
     * C5/C9 guards: applicability_status === REQUIRED; user_id !== null.
     */
    public function activate(
        User $actor,
        LegalFunctionAssignment $assignment,
    ): LegalFunctionAssignment {
        if ($assignment->status !== LegalFunctionStatus::INACTIVE) {
            throw new \InvalidArgumentException('Legal function must be INACTIVE to activate.');
        }

        if ($assignment->applicability_status !== LegalFunctionApplicability::REQUIRED) {
            throw new \InvalidArgumentException('Cannot activate: applicability_status must be REQUIRED.');
        }

        if ($assignment->user_id === null) {
            throw new \InvalidArgumentException('Cannot activate: user_id is required.');
        }

        return DB::transaction(function () use ($actor, $assignment) {
            $previousState = $this->auditSnapshot($assignment);

            $assignment->update([
                'status' => LegalFunctionStatus::ACTIVE,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::LEGAL_FUNCTION_ACTIVATED,
                $assignment,
                $previousState,
                $this->auditSnapshot($assignment),
            );

            return $assignment->refresh();
        });
    }

    /**
     * Suspend an active legal function assignment.
     */
    public function suspend(
        User $actor,
        LegalFunctionAssignment $assignment,
        string $reason,
    ): LegalFunctionAssignment {
        if ($assignment->status !== LegalFunctionStatus::ACTIVE) {
            throw new \InvalidArgumentException('Legal function must be ACTIVE to suspend.');
        }

        return DB::transaction(function () use ($actor, $assignment, $reason) {
            $previousState = $this->auditSnapshot($assignment);

            $assignment->update([
                'status' => LegalFunctionStatus::SUSPENDED,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::LEGAL_FUNCTION_SUSPENDED,
                $assignment,
                $previousState,
                array_merge($this->auditSnapshot($assignment), ['reason' => $reason]),
            );

            return $assignment->refresh();
        });
    }

    /**
     * Terminate a legal function assignment.
     */
    public function terminate(
        User $actor,
        LegalFunctionAssignment $assignment,
        string $reason,
    ): LegalFunctionAssignment {
        if (! in_array($assignment->status, [LegalFunctionStatus::ACTIVE, LegalFunctionStatus::SUSPENDED])) {
            throw new \InvalidArgumentException('Legal function must be ACTIVE or SUSPENDED to terminate.');
        }

        return DB::transaction(function () use ($actor, $assignment, $reason) {
            $previousState = $this->auditSnapshot($assignment);

            $assignment->update([
                'status' => LegalFunctionStatus::TERMINATED,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::LEGAL_FUNCTION_TERMINATED,
                $assignment,
                $previousState,
                array_merge($this->auditSnapshot($assignment), ['reason' => $reason]),
            );

            return $assignment->refresh();
        });
    }

    /**
     * Open an applicability review (DPO applicability).
     *
     * C13 idempotency: if an existing PENDING/REQUEST_MORE_EVIDENCE/ESCALATED
     * case exists for this subject + type, return it instead of creating a duplicate.
     */
    public function openApplicabilityReview(
        User $actor,
        LegalFunctionAssignment $assignment,
        string $ruleId,
    ): HumanReviewCase {
        return DB::transaction(function () use ($actor, $assignment, $ruleId) {
            $existing = HumanReviewCase::where('subject_type', LegalFunctionAssignment::class)
                ->where('subject_id', $assignment->id)
                ->where('type', HumanReviewType::DPO_APPLICABILITY)
                ->whereIn('status', [
                    HumanReviewStatus::PENDING,
                    HumanReviewStatus::REQUEST_MORE_EVIDENCE,
                    HumanReviewStatus::ESCALATED,
                ])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $previousState = $this->auditSnapshot($assignment);

            $activeKey = HumanReviewType::DPO_APPLICABILITY->value
                .':'.LegalFunctionAssignment::class
                .':'.$assignment->id;

            try {
                $case = HumanReviewCase::create([
                    'type' => HumanReviewType::DPO_APPLICABILITY,
                    'active_review_key' => $activeKey,
                    'rule_id' => $ruleId,
                    'trigger' => 'Legal function applicability review opened',
                    'subject_type' => LegalFunctionAssignment::class,
                    'subject_id' => $assignment->id,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if (str_contains($e->getMessage(), 'hrc_active_review_unique')) {
                    return HumanReviewCase::where('subject_type', LegalFunctionAssignment::class)
                        ->where('subject_id', $assignment->id)
                        ->where('type', HumanReviewType::DPO_APPLICABILITY)
                        ->whereIn('status', [
                            HumanReviewStatus::PENDING,
                            HumanReviewStatus::REQUEST_MORE_EVIDENCE,
                            HumanReviewStatus::ESCALATED,
                        ])
                        ->firstOrFail();
                }

                throw $e;
            }

            $assignment->update([
                'applicability_status' => LegalFunctionApplicability::LEGAL_REVIEW,
                'human_review_case_id' => $case->id,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::LEGAL_FUNCTION_APPLICABILITY_REVIEW_OPENED,
                $assignment,
                $previousState,
                array_merge($this->auditSnapshot($assignment), [
                    'human_review_case_id' => $case->id,
                ]),
            );

            return $case;
        });
    }

    /**
     * Apply a decided applicability review.
     *
     * C6: Case must exist, type = DPO_APPLICABILITY, subject matches,
     * status decided, decided_at set.
     */
    public function applyApplicabilityReview(
        User $actor,
        LegalFunctionAssignment $assignment,
        string $humanReviewCaseId,
    ): LegalFunctionAssignment {
        $case = HumanReviewCase::findOrFail($humanReviewCaseId);

        if ($case->type !== HumanReviewType::DPO_APPLICABILITY) {
            throw new \InvalidArgumentException('Review case is not DPO_APPLICABILITY type.');
        }

        if ($case->subject_type !== LegalFunctionAssignment::class || $case->subject_id !== $assignment->id) {
            throw new \InvalidArgumentException('Review case subject does not match this assignment.');
        }

        if ($case->status !== HumanReviewStatus::PENDING && $case->decided_at === null) {
            throw new \InvalidArgumentException('Review case must have a decision before applying.');
        }

        $newApplicability = $case->decision === 'REQUIRED'
            ? LegalFunctionApplicability::REQUIRED
            : LegalFunctionApplicability::NOT_REQUIRED;

        return DB::transaction(function () use ($actor, $assignment, $case, $newApplicability) {
            $previousState = $this->auditSnapshot($assignment);

            $case->update(['active_review_key' => null]);

            $assignment->update([
                'applicability_status' => $newApplicability,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::LEGAL_FUNCTION_APPLICABILITY_REVIEWED,
                $assignment,
                $previousState,
                $this->auditSnapshot($assignment),
            );

            return $assignment->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(LegalFunctionAssignment $assignment): array
    {
        return [
            'function_code' => $assignment->function_code,
            'function_category' => $assignment->function_category,
            'statutory_basis' => $assignment->statutory_basis,
            'appointment_basis' => $assignment->appointment_basis,
            'applicability_status' => $assignment->applicability_status?->value,
            'effective_from' => $assignment->effective_from?->format('Y-m-d'),
            'effective_until' => $assignment->effective_until?->format('Y-m-d'),
            'scope' => $assignment->scope,
            'status' => $assignment->status?->value,
            'human_review_case_id' => $assignment->human_review_case_id,
        ];
    }
}
