<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PseRegistrationApplicability;
use App\Enums\PseRegistrationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PSE Registration Registry record (PSE-REG-001/002/003).
 *
 * Registry-only governance record of the PSE (privat) registration with the
 * government. AJM records externally obtained registration evidence and
 * never generates registration numbers.
 *
 * Lifecycle (registration_status) is kept strictly separate from internal
 * legal applicability (applicability). Only registration_status ===
 * REGISTERED counts as registered — see isRegistered().
 */
class PSERegistration extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pse_registration';

    protected $fillable = [
        'pse_registration_number',
        'pse_type',
        'registered_at',
        'maintenance_due_at',
        'registration_status',
        'applicability',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
            'maintenance_due_at' => 'date',
            'registration_status' => PseRegistrationStatus::class,
            'applicability' => PseRegistrationApplicability::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PSERegistration $registration) {
            $registration->registration_status ??= PseRegistrationStatus::UNREGISTERED;
            $registration->applicability ??= PseRegistrationApplicability::UNRESOLVED;
        });
    }

    /**
     * Only a REGISTERED lifecycle status counts as a registered PSE.
     * Guards the forbidden behavior of claiming PSE-registered status
     * without a matching registration record (PSE-REG-001).
     */
    public function isRegistered(): bool
    {
        return $this->registration_status === PseRegistrationStatus::REGISTERED;
    }
}
