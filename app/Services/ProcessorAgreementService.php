<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgreementStatus;
use App\Enums\AuditAction;
use App\Enums\HumanReviewStatus;
use App\Enums\HumanReviewType;
use App\Enums\ProcessorRoleStatus;
use App\Models\HumanReviewCase;
use App\Models\ProcessorAgreement;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ProcessorAgreementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Create a new processor agreement.
     * Defaults: UNKNOWN + DRAFT + evidence_verified=false.
     */
    public function create(
        User $actor,
        array $data,
    ): ProcessorAgreement {
        return DB::transaction(function () use ($actor, $data) {
            $agreement = ProcessorAgreement::create([
                'counterparty_name' => $data['counterparty_name'],
                'counterparty_identifier' => $data['counterparty_identifier'] ?? null,
                'data_categories' => $data['data_categories'] ?? null,
                'processing_purposes' => $data['processing_purposes'] ?? null,
                'legal_basis' => $data['legal_basis'] ?? null,
                'evidence_reference' => $data['evidence_reference'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_until' => $data['effective_until'] ?? null,
                'notes' => $data['notes'] ?? null,
                'actor_user_id' => $actor->id,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::PROCESSOR_AGREEMENT_CREATED,
                $agreement,
                [],
                $this->auditSnapshot($agreement),
            );

            return $agreement;
        });
    }

    /**
     * Update processor agreement fields.
     *
     * C15/C21: When evidence-defining fields change and evidence was verified,
     * reset evidence_verified + transition processor_role_status to REQUIRE_REVIEW.
     *
     * C23: effective_until does NOT invalidate processor evidence.
     */
    public function update(
        User $actor,
        ProcessorAgreement $agreement,
        array $data,
    ): ProcessorAgreement {
        return DB::transaction(function () use ($actor, $agreement, $data) {
            $previousState = $this->auditSnapshot($agreement);

            $evidenceDefining = ['evidence_reference', 'effective_from', 'counterparty_identifier'];
            $evidenceChanged = false;

            foreach ($evidenceDefining as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== $agreement->{$field}) {
                    $evidenceChanged = true;
                    break;
                }
            }

            $updateData = [];

            foreach ($data as $key => $value) {
                if (in_array($key, ['processor_role_status', 'agreement_status', 'evidence_verified'])) {
                    continue;
                }
                $updateData[$key] = $value;
            }

            if ($evidenceChanged && $agreement->evidence_verified === true) {
                $updateData['evidence_verified'] = false;
                $updateData['evidence_verified_at'] = null;

                if (in_array($agreement->processor_role_status, [
                    ProcessorRoleStatus::PROCESSOR,
                    ProcessorRoleStatus::NOT_PROCESSOR,
                ])) {
                    $updateData['processor_role_status'] = ProcessorRoleStatus::REQUIRE_REVIEW;
                }
            }

            $agreement->update($updateData);

            $this->auditLogger->log(
                $actor,
                AuditAction::PROCESSOR_AGREEMENT_UPDATED,
                $agreement,
                $previousState,
                $this->auditSnapshot($agreement),
            );

            return $agreement->refresh();
        });
    }

    /**
     * Change agreement lifecycle status.
     *
     * C18: agreement_status = persisted lifecycle state.
     * isActiveAgreement() = derived temporal effectiveness.
     */
    public function changeAgreementStatus(
        User $actor,
        ProcessorAgreement $agreement,
        AgreementStatus $targetStatus,
        ?string $reason = null,
    ): ProcessorAgreement {
        $this->validateAgreementTransition($agreement, $targetStatus);

        return DB::transaction(function () use ($actor, $agreement, $targetStatus, $reason) {
            $previousState = $this->auditSnapshot($agreement);

            $agreement->update([
                'agreement_status' => $targetStatus,
            ]);

            $newState = $this->auditSnapshot($agreement);
            if ($reason !== null) {
                $newState['reason'] = $reason;
            }

            $this->auditLogger->log(
                $actor,
                AuditAction::PROCESSOR_AGREEMENT_STATUS_CHANGED,
                $agreement,
                $previousState,
                $newState,
            );

            return $agreement->refresh();
        });
    }

    /**
     * Verify evidence for processor role determination.
     *
     * C11: Guard evidence_reference not null, evidence_verified === false.
     */
    public function verifyEvidence(
        User $actor,
        ProcessorAgreement $agreement,
        string $factualBasis,
    ): ProcessorAgreement {
        if ($agreement->evidence_reference === null) {
            throw new \InvalidArgumentException('Cannot verify evidence: evidence_reference is required.');
        }

        if ($agreement->evidence_verified === true) {
            throw new \InvalidArgumentException('Evidence already verified. Update evidence_reference to re-verify.');
        }

        return DB::transaction(function () use ($actor, $agreement, $factualBasis) {
            $previousState = $this->auditSnapshot($agreement);

            $agreement->update([
                'evidence_verified' => true,
                'evidence_verified_at' => now(),
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::PROCESSOR_AGREEMENT_EVIDENCE_VERIFIED,
                $agreement,
                $previousState,
                array_merge($this->auditSnapshot($agreement), [
                    'factual_basis' => $factualBasis,
                ]),
            );

            return $agreement->refresh();
        });
    }

    /**
     * Deterministic processor role determination.
     *
     * C10/C11/C21: Guard processor_role_status === UNKNOWN, evidence verified,
     * no unresolved HumanReviewCase.
     */
    public function determineRole(
        User $actor,
        ProcessorAgreement $agreement,
        ProcessorRoleStatus $targetRole,
    ): ProcessorAgreement {
        if ($agreement->processor_role_status !== ProcessorRoleStatus::UNKNOWN) {
            throw new \InvalidArgumentException(
                'Cannot determine role: status must be UNKNOWN. Current status: '
                .$agreement->processor_role_status->value
            );
        }

        if ($agreement->evidence_reference === null) {
            throw new \InvalidArgumentException('Cannot determine role: evidence_reference is required.');
        }

        if ($agreement->effective_from === null) {
            throw new \InvalidArgumentException('Cannot determine role: effective_from is required.');
        }

        if ($agreement->evidence_verified !== true) {
            throw new \InvalidArgumentException('Cannot determine role: evidence must be verified first.');
        }

        $unresolved = HumanReviewCase::where('subject_type', ProcessorAgreement::class)
            ->where('subject_id', $agreement->id)
            ->whereIn('status', [
                HumanReviewStatus::PENDING,
                HumanReviewStatus::REQUEST_MORE_EVIDENCE,
                HumanReviewStatus::ESCALATED,
            ])
            ->exists();

        if ($unresolved) {
            throw new \InvalidArgumentException('Cannot determine role: unresolved HumanReviewCase exists.');
        }

        if (! in_array($targetRole, [ProcessorRoleStatus::PROCESSOR, ProcessorRoleStatus::NOT_PROCESSOR])) {
            throw new \InvalidArgumentException('Target role must be PROCESSOR or NOT_PROCESSOR.');
        }

        return DB::transaction(function () use ($actor, $agreement, $targetRole) {
            $previousState = $this->auditSnapshot($agreement);

            $agreement->update([
                'processor_role_status' => $targetRole,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::PROCESSOR_AGREEMENT_ROLE_DETERMINED,
                $agreement,
                $previousState,
                $this->auditSnapshot($agreement),
            );

            return $agreement->refresh();
        });
    }

    /**
     * Open a processor role review (initial or reassessment).
     *
     * C13 idempotency: if existing pending case exists, return it.
     * C14 reassessment: accepts UNKNOWN, PROCESSOR, NOT_PROCESSOR as current state.
     */
    public function openRoleReview(
        User $actor,
        ProcessorAgreement $agreement,
        string $ruleId,
    ): HumanReviewCase {
        return DB::transaction(function () use ($actor, $agreement, $ruleId) {
            $existing = HumanReviewCase::where('subject_type', ProcessorAgreement::class)
                ->where('subject_id', $agreement->id)
                ->where('type', HumanReviewType::PROCESSOR_ROLE)
                ->whereIn('status', [
                    HumanReviewStatus::PENDING,
                    HumanReviewStatus::REQUEST_MORE_EVIDENCE,
                    HumanReviewStatus::ESCALATED,
                ])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $previousState = $this->auditSnapshot($agreement);

            $activeKey = HumanReviewType::PROCESSOR_ROLE->value
                .':'.ProcessorAgreement::class
                .':'.$agreement->id;

            try {
                $case = HumanReviewCase::create([
                    'type' => HumanReviewType::PROCESSOR_ROLE,
                    'active_review_key' => $activeKey,
                    'rule_id' => $ruleId,
                    'trigger' => 'Processor role review opened',
                    'subject_type' => ProcessorAgreement::class,
                    'subject_id' => $agreement->id,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if (str_contains($e->getMessage(), 'hrc_active_review_unique')) {
                    return HumanReviewCase::where('subject_type', ProcessorAgreement::class)
                        ->where('subject_id', $agreement->id)
                        ->where('type', HumanReviewType::PROCESSOR_ROLE)
                        ->whereIn('status', [
                            HumanReviewStatus::PENDING,
                            HumanReviewStatus::REQUEST_MORE_EVIDENCE,
                            HumanReviewStatus::ESCALATED,
                        ])
                        ->firstOrFail();
                }

                throw $e;
            }

            $agreement->update([
                'processor_role_status' => ProcessorRoleStatus::REQUIRE_REVIEW,
                'human_review_case_id' => $case->id,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::PROCESSOR_AGREEMENT_ROLE_REVIEW_OPENED,
                $agreement,
                $previousState,
                array_merge($this->auditSnapshot($agreement), [
                    'human_review_case_id' => $case->id,
                ]),
            );

            return $case;
        });
    }

    /**
     * Apply a decided processor role review.
     *
     * C2: Case must exist, type = PROCESSOR_ROLE, subject matches, decided.
     */
    public function applyRoleReview(
        User $actor,
        ProcessorAgreement $agreement,
        string $humanReviewCaseId,
    ): ProcessorAgreement {
        $case = HumanReviewCase::findOrFail($humanReviewCaseId);

        if ($case->type !== HumanReviewType::PROCESSOR_ROLE) {
            throw new \InvalidArgumentException('Review case is not PROCESSOR_ROLE type.');
        }

        if ($case->subject_type !== ProcessorAgreement::class || $case->subject_id !== $agreement->id) {
            throw new \InvalidArgumentException('Review case subject does not match this agreement.');
        }

        if ($case->status !== HumanReviewStatus::PENDING && $case->decided_at === null) {
            throw new \InvalidArgumentException('Review case must have a decision before applying.');
        }

        $newRole = $case->decision === 'PROCESSOR'
            ? ProcessorRoleStatus::PROCESSOR
            : ProcessorRoleStatus::NOT_PROCESSOR;

        return DB::transaction(function () use ($actor, $agreement, $case, $newRole) {
            $previousState = $this->auditSnapshot($agreement);

            $case->update(['active_review_key' => null]);

            $agreement->update([
                'processor_role_status' => $newRole,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::PROCESSOR_AGREEMENT_ROLE_REVIEWED,
                $agreement,
                $previousState,
                $this->auditSnapshot($agreement),
            );

            return $agreement->refresh();
        });
    }

    private function validateAgreementTransition(
        ProcessorAgreement $agreement,
        AgreementStatus $target,
    ): void {
        $current = $agreement->agreement_status;

        $allowed = [
            AgreementStatus::DRAFT->value => [
                AgreementStatus::ACTIVE,
                AgreementStatus::TERMINATED,
            ],
            AgreementStatus::ACTIVE->value => [
                AgreementStatus::SUSPENDED,
                AgreementStatus::EXPIRED,
                AgreementStatus::TERMINATED,
            ],
            AgreementStatus::SUSPENDED->value => [
                AgreementStatus::ACTIVE,
                AgreementStatus::TERMINATED,
            ],
        ];

        $transitions = $allowed[$current->value] ?? [];

        if (! in_array($target, $transitions)) {
            throw new \InvalidArgumentException(
                "Invalid agreement status transition from {$current->value} to {$target->value}"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(ProcessorAgreement $agreement): array
    {
        return [
            'counterparty_name' => $agreement->counterparty_name,
            'processor_role_status' => $agreement->processor_role_status->value,
            'agreement_status' => $agreement->agreement_status->value,
            'evidence_verified' => $agreement->evidence_verified,
            'data_categories' => $agreement->data_categories,
            'processing_purposes' => $agreement->processing_purposes,
            'legal_basis' => $agreement->legal_basis,
            'effective_from' => $agreement->effective_from?->format('Y-m-d'),
            'effective_until' => $agreement->effective_until?->format('Y-m-d'),
            'human_review_case_id' => $agreement->human_review_case_id,
        ];
    }
}
