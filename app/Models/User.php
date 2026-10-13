<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\B2gEvidenceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'full_name',
        'company_name',
        'npwp_number',
        'address',
        'phone_number',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'requested_role' => UserRole::class,
            'email_verified_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->role ??= UserRole::BUYER_B2B;
            $user->status ??= UserStatus::PENDING;
        });
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Stable, email-bound hash used to validate verification links. Changing the
     * email address invalidates any previously issued link.
     */
    public function emailVerificationHash(): string
    {
        return sha1((string) $this->email);
    }

    /**
     * The versioned B2G evidence submissions for this account, newest first.
     */
    public function b2gEvidenceSubmissions(): HasMany
    {
        return $this->hasMany(B2gEvidenceSubmission::class)->orderByDesc('version');
    }

    public function latestAcceptedB2gEvidence(): ?B2gEvidenceSubmission
    {
        return $this->b2gEvidenceSubmissions()
            ->where('status', B2gEvidenceStatus::ACCEPTED->value)
            ->first();
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
