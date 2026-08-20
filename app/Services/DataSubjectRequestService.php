<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DsrStatus;
use App\Enums\StatutoryTimerStatus;
use App\Enums\VerificationStatus;
use App\Events\HumanReviewCaseCreated;
use App\Models\DataSubjectRequest;
use App\Models\HumanReviewCase;
use App\Models\StatutoryTimer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DataSubjectRequestService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly IdentityVerificationService $identityService,
        private readonly LawfulBasisResolver $lawfulBasisResolver,
        private readonly RetentionResolver $retentionResolver,
        private readonly StatutoryTimerService $timerService,
    ) {}

    /**
     * Create a new DSR. Sets initial status = RECEIVED.
     */
    public function createDsr(
        User $actor,
        ?User $subject,
        string $rightCode,
        string $channel,
        ?array $requestInput = null,
        string $subjectType = 'USER',
    ): DataSubjectRequest {
        $verificationStatus = $this->identityService->determineSubmissionStatus($actor, $subject);
        $confidence = $this->identityService->confidenceForSubmission(
            $subject !== null && $actor->id === $subject->id,
        );

        $dsr = DataSubjectRequest::create([
            'subject_user_id' => $subject?->id,
            'subject_type' => $subjectType,
            'right_code' => $rightCode,
            'channel' => $channel,
            'request_input' => $requestInput,
            'identity_verification_status' => $verificationStatus,
            'identity_confidence' => $confidence,
            'status' => DsrStatus::RECEIVED,
            'applicability_status' => 'APPLICABILITY_UNKNOWN',
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::DSR_CREATED,
            $dsr,
            [],
            [
                'subject_type' => $subjectType,
                'right_code' => $rightCode,
                'channel' => $channel,
                'status' => DsrStatus::RECEIVED->value,
            ],
        );

        return $dsr;
    }

    /**
     * Verify identity for a DSR.
     */
    public function verifyIdentity(
        DataSubjectRequest $dsr,
        User $actor,
        string $status,
        ?array $meta = null,
    ): DataSubjectRequest {
        $previousState = [
            'identity_verification_status' => $dsr->identity_verification_status->value,
        ];

        $newStatus = VerificationStatus::from($status);

        $dsr->update([
            'identity_verification_status' => $newStatus,
            'identity_verification_meta' => $meta,
        ]);

        $newState = [
            'identity_verification_status' => $newStatus->value,
            'identity_verification_meta' => $meta,
        ];

        $this->auditLogger->log(
            $actor,
            AuditAction::DSR_IDENTITY_VERIFIED,
            $dsr,
            $previousState,
            $newState,
        );

        if ($newStatus === VerificationStatus::VERIFIED && $dsr->status === DsrStatus::RECEIVED) {
            $dsr->update(['status' => DsrStatus::IDENTITY_VERIFIED]);
        }

        return $dsr->fresh();
    }

    /**
     * Classify the applicable right for a DSR.
     */
    public function classifyRight(
        DataSubjectRequest $dsr,
        User $actor,
        string $applicabilityStatus,
    ): DataSubjectRequest {
        $previousState = ['applicability_status' => $dsr->applicability_status];

        $dsr->update(['applicability_status' => $applicabilityStatus]);

        $this->auditLogger->log(
            $actor,
            AuditAction::DSR_RIGHT_CLASSIFIED,
            $dsr,
            $previousState,
            ['applicability_status' => $applicabilityStatus],
        );

        return $dsr->fresh();
    }

    /**
     * Resolve the lawful basis for affected processing.
     */
    public function resolveLawfulBasis(
        DataSubjectRequest $dsr,
        User $actor,
        string $processingClass,
    ): DataSubjectRequest {
        $result = $this->lawfulBasisResolver->resolveForUser($processingClass);

        $previousState = [
            'processing_lawful_basis_evaluated' => $dsr->processing_lawful_basis_evaluated,
        ];

        $dsr->update([
            'processing_lawful_basis_evaluated' => [
                'processing_class' => $processingClass,
                'basis' => $result['basis'],
                'rule_id' => $result['rule_id'],
                'requires_review' => $result['requires_review'],
            ],
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::DSR_LAWFUL_BASIS_RESOLVED,
            $dsr,
            $previousState,
            $dsr->processing_lawful_basis_evaluated,
        );

        return $dsr->fresh();
    }

    /**
     * Open a human review case for a DSR.
     */
    public function openHumanReview(
        DataSubjectRequest $dsr,
        User $actor,
        string $decisionType,
        string $ruleId,
        ?string $notes = null,
    ): DataSubjectRequest {
        $case = HumanReviewCase::create([
            'type' => $decisionType,
            'rule_id' => $ruleId,
            'trigger' => 'DSR:'.$dsr->right_code,
            'subject_type' => $dsr->subject_type?->value,
            'subject_id' => $dsr->subject_user_id,
            'reason' => $notes,
            'reviewed_by' => $actor->id,
            'status' => 'PENDING',
        ]);

        $previousState = ['status' => $dsr->status->value, 'human_review_case_id' => $dsr->human_review_case_id];

        $dsr->update([
            'status' => DsrStatus::REVIEW_REQUIRED,
            'human_review_case_id' => $case->id,
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::DSR_HUMAN_REVIEW_OPENED,
            $dsr,
            $previousState,
            [
                'status' => DsrStatus::REVIEW_REQUIRED->value,
                'human_review_case_id' => $case->id,
            ],
        );

        event(new HumanReviewCaseCreated($case));

        return $dsr->fresh();
    }

    /**
     * Fulfill a DSR.
     */
    public function fulfill(
        DataSubjectRequest $dsr,
        User $actor,
        ?string $notes = null,
    ): DataSubjectRequest {
        $previousState = ['status' => $dsr->status->value];

        $dsr->update([
            'status' => DsrStatus::FULFILLED,
            'handled_by' => $actor->id,
            'decision_notes' => $notes,
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::DSR_FULFILLED,
            $dsr,
            $previousState,
            ['status' => DsrStatus::FULFILLED->value, 'handled_by' => $actor->id],
        );

        return $dsr->fresh();
    }

    /**
     * Reject a DSR.
     */
    public function reject(
        DataSubjectRequest $dsr,
        User $actor,
        ?string $notes = null,
    ): DataSubjectRequest {
        $previousState = ['status' => $dsr->status->value];

        $dsr->update([
            'status' => DsrStatus::REJECTED,
            'handled_by' => $actor->id,
            'decision_notes' => $notes,
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::DSR_REJECTED,
            $dsr,
            $previousState,
            ['status' => DsrStatus::REJECTED->value, 'handled_by' => $actor->id],
        );

        return $dsr->fresh();
    }

    /**
     * Close a DSR (final state).
     */
    public function close(
        DataSubjectRequest $dsr,
        User $actor,
        ?string $notes = null,
    ): DataSubjectRequest {
        $previousState = ['status' => $dsr->status->value];

        $dsr->update([
            'status' => DsrStatus::CLOSED,
            'handled_by' => $actor->id,
            'decision_notes' => $notes,
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::DSR_CLOSED,
            $dsr,
            $previousState,
            ['status' => DsrStatus::CLOSED->value, 'handled_by' => $actor->id],
        );

        return $dsr->fresh();
    }

    /**
     * Accept a PDP-RIGHT-007 restriction request.
     *
     * This activates the processing restriction and starts the statutory timer.
     * Timer is created within the same transaction — failure rolls back.
     * Does NOT start timer merely because DSR exists; only on acceptance.
     */
    public function acceptRestriction(
        DataSubjectRequest $dsr,
        User $actor,
    ): DataSubjectRequest {
        return DB::transaction(function () use ($dsr, $actor) {
            $previousState = ['status' => $dsr->status->value];

            $dsr->update([
                'status' => DsrStatus::PROCESSING,
                'handled_by' => $actor->id,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::DSR_RESTRICTION_ACCEPTED,
                $dsr,
                $previousState,
                ['status' => DsrStatus::PROCESSING->value, 'handled_by' => $actor->id],
            );

            $this->timerService->startForRestrictionSuspension($dsr);

            return $dsr->fresh();
        });
    }

    /**
     * Confirm that a PDP-RIGHT-007 restriction has been actually completed.
     *
     * This is the ONLY way to mark the restriction timer as MET.
     * Administrative DSR fulfillment does NOT mark the timer MET.
     */
    public function confirmRestrictionCompletion(
        DataSubjectRequest $dsr,
        User $actor,
        ?string $notes = null,
    ): DataSubjectRequest {
        $timer = StatutoryTimer::where('ref_type', DataSubjectRequest::class)
            ->where('ref_id', $dsr->id)
            ->where('status', StatutoryTimerStatus::RUNNING)
            ->first();

        if ($timer) {
            $this->timerService->markMet($timer);
        }

        $previousState = ['status' => $dsr->status->value];

        $dsr->update([
            'status' => DsrStatus::FULFILLED,
            'handled_by' => $actor->id,
            'decision_notes' => $notes,
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::DSR_FULFILLED,
            $dsr,
            $previousState,
            ['status' => DsrStatus::FULFILLED->value, 'handled_by' => $actor->id],
        );

        return $dsr->fresh();
    }
}
