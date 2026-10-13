<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\B2gEvidenceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Authoritative, append-only record of B2G evidence: one row per submitted
 * version. Later submissions never overwrite earlier ones; the previous active
 * version is marked SUPERSEDED and retained for review history.
 */
class B2gEvidenceSubmission extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'b2g_evidence_submissions';

    protected $fillable = [
        'user_id',
        'version',
        'submitted_by',
        'disk',
        'path',
        'original_filename',
        'mime',
        'size',
        'evidence_type',
        'institution_name',
        'work_unit',
        'position',
        'reference',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_reason',
    ];

    protected $hidden = [
        // Storage location is never exposed to clients; downloads are mediated
        // through an authorization-checked route instead.
        'path',
        'disk',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size' => 'integer',
            'status' => B2gEvidenceStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
