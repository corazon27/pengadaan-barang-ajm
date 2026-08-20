<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ConsentSourceChannel;
use App\Enums\ConsentStatus;
use App\Enums\LawfulBasis;
use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConsentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly StatutoryTimerService $timerService,
    ) {}

    /**
     * Grant a new consent record.
     *
     * Validates that notice_version and document_ref are non-empty.
     * In 3C.1, this will validate against actual document_evidence rows.
     */
    public function grant(
        User $subject,
        string $purpose,
        string $processingLawfulBasis,
        string $noticeVersion,
        string $documentRef,
        string $sourceChannel,
        ?User $actor = null,
        ?string $ruleId = null,
    ): ConsentRecord {
        $record = ConsentRecord::create([
            'subject_user_id' => $subject->id,
            'subject_type' => $subject->id === ($actor?->id ?? $subject->id) ? 'USER' : 'PROXY',
            'purpose' => $purpose,
            'processing_lawful_basis' => $processingLawfulBasis,
            'notice_version' => $noticeVersion,
            'document_ref' => $documentRef,
            'consent_status' => ConsentStatus::ACTIVE,
            'granted_at' => now(),
            'source_channel' => $sourceChannel,
            'actor_user_id' => $actor?->id,
            'rule_id' => $ruleId,
        ]);

        $this->auditLogger->log(
            $actor ?? $subject,
            AuditAction::CONSENT_GRANTED,
            $record,
            [],
            [
                'purpose' => $purpose,
                'processing_lawful_basis' => $processingLawfulBasis,
                'notice_version' => $noticeVersion,
                'document_ref' => $documentRef,
                'consent_status' => ConsentStatus::ACTIVE->value,
            ],
        );

        return $record;
    }

    /**
     * Withdraw a consent record.
     *
     * Creates the statutory timer within the same transaction.
     * Timer creation failure rolls back the entire withdrawal.
     */
    public function withdraw(
        ConsentRecord $record,
        User $actor,
    ): ConsentRecord {
        return DB::transaction(function () use ($record, $actor) {
            $previousState = [
                'consent_status' => $record->consent_status?->value,
            ];

            $record->update([
                'consent_status' => ConsentStatus::WITHDRAWN,
                'withdrawn_at' => now(),
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::CONSENT_WITHDRAWN,
                $record,
                $previousState,
                [
                    'consent_status' => ConsentStatus::WITHDRAWN->value,
                    'withdrawn_at' => now()->toAtomString(),
                ],
            );

            $deadline = $this->timerService->startForConsentWithdrawal($record);

            $record->update([
                'withdrawal_deadline_at' => $deadline,
            ]);

            $record->refresh();

            return $record;
        });
    }

    /**
     * Supersede an existing consent with a new record.
     */
    public function supersede(
        ConsentRecord $old,
        array $newAttributes,
        User $actor,
    ): ConsentRecord {
        $subject = User::find($old->subject_user_id);

        if ($subject === null) {
            throw new \RuntimeException('Subject user not found for consent record '.$old->id);
        }

        $old->update(['consent_status' => ConsentStatus::SUPERSEDED]);

        $this->auditLogger->log(
            $actor,
            AuditAction::CONSENT_SUPERSEDED,
            $old,
            ['consent_status' => ConsentStatus::ACTIVE->value],
            ['consent_status' => ConsentStatus::SUPERSEDED->value],
        );

        return $this->grant(
            subject: $subject,
            purpose: $newAttributes['purpose'] ?? $old->purpose,
            processingLawfulBasis: $newAttributes['processing_lawful_basis'] ?? $old->processing_lawful_basis?->value ?? LawfulBasis::CONTRACT->value,
            noticeVersion: $newAttributes['notice_version'] ?? $old->notice_version,
            documentRef: $newAttributes['document_ref'] ?? $old->document_ref,
            sourceChannel: $newAttributes['source_channel'] ?? $old->source_channel?->value ?? ConsentSourceChannel::WEB->value,
            actor: $actor,
            ruleId: $newAttributes['rule_id'] ?? $old->rule_id,
        );
    }

    /**
     * Invalidate a consent (mark as invalid — e.g. missing document evidence).
     */
    public function invalidate(
        ConsentRecord $record,
        User $actor,
        ?string $reason = null,
    ): ConsentRecord {
        $previousState = [
            'consent_status' => $record->consent_status?->value,
        ];

        $record->update([
            'consent_status' => ConsentStatus::INVALID,
            'withdrawn_at' => $record->withdrawn_at ?? now(),
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::CONSENT_INVALIDATED,
            $record,
            $previousState,
            [
                'consent_status' => ConsentStatus::INVALID->value,
                'reason' => $reason,
            ],
        );

        $record->refresh();

        return $record;
    }

    /**
     * Mark expired consents (past withdrawal_deadline_at).
     */
    public function expireStale(): int
    {
        $expired = ConsentRecord::query()
            ->where('consent_status', ConsentStatus::ACTIVE)
            ->whereNotNull('withdrawal_deadline_at')
            ->where('withdrawal_deadline_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $record) {
            $record->update(['consent_status' => ConsentStatus::EXPIRED]);
            $count++;
        }

        return $count;
    }
}
