<?php

declare(strict_types=1);

namespace App\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LegalFunctionAssignment $assignment) {
            $assignment->applicability_status ??= 'PENDING_LEGAL_REVIEW';
            $assignment->status ??= 'INACTIVE';
        });
    }
}
