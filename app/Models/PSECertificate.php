<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PseCertificateStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PSE Electronic Certificate Registry record (PSE-CERT-001).
 *
 * Registry of the Sertifikat Elektronik issued to AJM by PSrE Indonesia.
 * AJM never issues certificates internally and never generates certificate
 * serials — certificate_number is operator-recorded external evidence.
 *
 * certificate_status is the EXTERNAL certificate lifecycle; verification_status
 * is the INTERNAL verification state. Neither is inferred from the other,
 * and this registry is distinct from user TTE / BSrE / Sertifikat Keandalan.
 */
class PSECertificate extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pse_certificates';

    protected $fillable = [
        'certificate_number',
        'psre_provider',
        'issued_at',
        'expires_at',
        'certificate_status',
        'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'certificate_status' => PseCertificateStatus::class,
            'verification_status' => VerificationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PSECertificate $certificate) {
            $certificate->certificate_status ??= PseCertificateStatus::PENDING;
            $certificate->verification_status ??= VerificationStatus::UNVERIFIED;
        });
    }

    /**
     * Deterministic expiry flag (PSE-CERT-001). Expired when the external
     * lifecycle is EXPIRED, or when an ACTIVE certificate's recorded
     * expires_at has already passed. A cert expiring today is still valid.
     */
    public function isExpired(): bool
    {
        if ($this->certificate_status === PseCertificateStatus::EXPIRED) {
            return true;
        }

        if ($this->certificate_status === PseCertificateStatus::ACTIVE && $this->expires_at !== null) {
            return $this->expires_at->lt(now()->startOfDay());
        }

        return false;
    }
}
