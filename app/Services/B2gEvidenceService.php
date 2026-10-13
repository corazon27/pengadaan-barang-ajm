<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\B2gEvidenceStatus;
use App\Models\B2gEvidenceSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Manages B2G evidence versions and review. Files live on the private
 * `documents` disk and are only reachable through an authorization-checked,
 * audited download. Version numbers are allocated under a row lock on the
 * applicant so concurrent submissions cannot silently collide (and the unique
 * (user_id, version) constraint is the hard backstop).
 */
class B2gEvidenceService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Append a new evidence version, superseding any previously active version.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(User $applicant, array $data, UploadedFile $file, ?User $submitter = null): B2gEvidenceSubmission
    {
        $path = Storage::disk('documents')->putFile("b2g/evidence/{$applicant->getKey()}", $file);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Gagal menyimpan berkas bukti.');
        }

        return DB::transaction(function () use ($applicant, $data, $file, $path, $submitter): B2gEvidenceSubmission {
            // Serialize version allocation per applicant. Locking the parent row
            // also covers the first submission, where no child rows exist yet.
            User::query()->whereKey($applicant->getKey())->lockForUpdate()->firstOrFail();

            $nextVersion = (int) B2gEvidenceSubmission::query()
                ->where('user_id', $applicant->getKey())
                ->max('version') + 1;

            B2gEvidenceSubmission::query()
                ->where('user_id', $applicant->getKey())
                ->whereIn('status', [
                    B2gEvidenceStatus::SUBMITTED->value,
                    B2gEvidenceStatus::UNDER_REVIEW->value,
                    B2gEvidenceStatus::ACCEPTED->value,
                ])
                ->update(['status' => B2gEvidenceStatus::SUPERSEDED->value]);

            $submission = B2gEvidenceSubmission::create([
                'user_id' => $applicant->getKey(),
                'version' => $nextVersion,
                'submitted_by' => $submitter?->getKey() ?? $applicant->getKey(),
                'disk' => 'documents',
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime' => (string) $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
                'evidence_type' => $data['evidence_type'],
                'institution_name' => $data['institution_name'] ?? null,
                'work_unit' => $data['work_unit'] ?? null,
                'position' => $data['position'] ?? null,
                'reference' => $data['reference'] ?? null,
                'status' => B2gEvidenceStatus::SUBMITTED,
            ]);

            $this->auditLogger->strictLog($submitter, AuditAction::B2G_EVIDENCE_SUBMITTED, $submission, [], [
                'user_id' => $applicant->getKey(),
                'submission_id' => $submission->getKey(),
                'version' => $nextVersion,
                'status' => B2gEvidenceStatus::SUBMITTED->value,
            ]);

            return $submission;
        });
    }

    /**
     * Record an administrator's review decision. Accepting evidence does not by
     * itself grant BUYER_B2G — the role is granted only through approval.
     */
    public function review(User $actor, B2gEvidenceSubmission $submission, B2gEvidenceStatus $decision, string $reason): B2gEvidenceSubmission
    {
        if (! in_array($decision, [B2gEvidenceStatus::ACCEPTED, B2gEvidenceStatus::REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Keputusan tinjauan harus ACCEPTED atau REJECTED.',
            ]);
        }

        return DB::transaction(function () use ($actor, $submission, $decision, $reason): B2gEvidenceSubmission {
            $locked = B2gEvidenceSubmission::query()
                ->whereKey($submission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, [B2gEvidenceStatus::SUPERSEDED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Versi bukti ini sudah digantikan dan tidak dapat ditinjau.',
                ]);
            }

            $previousStatus = $locked->status->value;

            $locked->forceFill([
                'status' => $decision,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_reason' => $reason,
            ])->save();

            $this->auditLogger->strictLog($actor, AuditAction::B2G_EVIDENCE_REVIEWED, $locked, [
                'submission_id' => $locked->getKey(),
                'status' => $previousStatus,
            ], [
                'submission_id' => $locked->getKey(),
                'status' => $decision->value,
                'decision_reason' => $reason,
            ]);

            return $locked;
        });
    }

    /**
     * Record an evidence access event BEFORE any bytes are served. This is a
     * strict write: if it fails, the exception propagates and the caller must not
     * serve the document.
     */
    public function recordAccess(User $actor, B2gEvidenceSubmission $submission): void
    {
        $this->auditLogger->strictLog($actor, AuditAction::B2G_EVIDENCE_ACCESSED, $submission, [], [
            'submission_id' => $submission->getKey(),
            'user_id' => $submission->user_id,
            'access' => 'download',
        ]);
    }
}
