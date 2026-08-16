<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HumanReviewStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The SINGLE canonical human-review abstraction. Every review type
 * (TAX, SUPPLIER_ELIGIBILITY, PDP, CERTIFICATION, ...) is stored here.
 * No parallel review engines exist.
 */
class HumanReviewCase extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'rule_id',
        'trigger',
        'subject_type',
        'subject_id',
        'evidence_snapshot',
        'capability_required',
        'legal_function_required',
        'decision',
        'reason',
        'status',
        'reviewed_by',
        'decided_at',
        'expires_at',
        're_review_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_snapshot' => 'array',
            'status' => HumanReviewStatus::class,
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
            're_review_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HumanReviewCase $case) {
            $case->status ??= HumanReviewStatus::PENDING;
        });
    }
}
