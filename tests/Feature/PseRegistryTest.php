<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\PseCertificateStatus;
use App\Enums\PseRegistrationApplicability;
use App\Enums\PseRegistrationStatus;
use App\Enums\VerificationStatus;
use App\Models\PSECertificate;
use App\Models\PSERegistration;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3B.1 — PSE Registration Registry (PSE-REG-001/002/003) and
 * PSE Electronic Certificate Registry (PSE-CERT-001).
 *
 * Registry-only, governance records. AJM never issues certificates
 * internally and never generates certificate serials; certificate_number is
 * operator-recorded evidence from the external PSrE Indonesia lifecycle.
 */
class PseRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_pse_registration_persists_with_defaults(): void
    {
        $registration = PSERegistration::create(['pse_type' => 'PRIVAT']);

        $this->assertSame(PseRegistrationStatus::UNREGISTERED, $registration->registration_status);
        $this->assertSame(PseRegistrationApplicability::UNRESOLVED, $registration->applicability);
        $this->assertNull($registration->pse_registration_number);
        $this->assertDatabaseHas('pse_registration', [
            'id' => $registration->id,
            'pse_type' => 'PRIVAT',
            'registration_status' => PseRegistrationStatus::UNREGISTERED->value,
            'applicability' => PseRegistrationApplicability::UNRESOLVED->value,
        ]);
    }

    public function test_pse_registration_records_government_registration(): void
    {
        $registration = PSERegistration::create([
            'pse_type' => 'PRIVAT',
            'pse_registration_number' => '012345.0001.2026',
            'registered_at' => '2026-03-01',
            'maintenance_due_at' => '2027-03-01',
            'registration_status' => PseRegistrationStatus::REGISTERED,
        ]);

        $this->assertTrue($registration->isRegistered());
        $this->assertSame('012345.0001.2026', $registration->pse_registration_number);
        $this->assertSame('2026-03-01', $registration->registered_at->toDateString());
        $this->assertDatabaseHas('pse_registration', [
            'id' => $registration->id,
            'registration_status' => PseRegistrationStatus::REGISTERED->value,
        ]);
    }

    public function test_pse_registration_lifecycle_is_distinct_from_applicability(): void
    {
        $registration = PSERegistration::create([
            'pse_type' => 'PRIVAT',
            'registration_status' => PseRegistrationStatus::REGISTERED,
            'applicability' => PseRegistrationApplicability::PENDING_LEGAL_REVIEW,
        ]);

        $fresh = $registration->fresh();

        $this->assertSame(PseRegistrationStatus::REGISTERED, $fresh->registration_status);
        $this->assertSame(PseRegistrationApplicability::PENDING_LEGAL_REVIEW, $fresh->applicability);
    }

    public function test_pse_registration_data_maintenance_is_recorded(): void
    {
        $registration = PSERegistration::create(['pse_type' => 'PRIVAT']);

        $registration->update([
            'maintenance_due_at' => now()->addMonths(6)->toDateString(),
            'pse_registration_number' => 'RE-2026-0042',
        ]);

        $this->assertSame('RE-2026-0042', $registration->pse_registration_number);
        $this->assertNotNull($registration->maintenance_due_at);
    }

    public function test_only_registered_status_counts_as_registered(): void
    {
        $unregistered = PSERegistration::create(['pse_type' => 'PRIVAT']);
        $this->assertFalse($unregistered->isRegistered());

        foreach ([
            PseRegistrationStatus::PENDING,
            PseRegistrationStatus::SUSPENDED,
            PseRegistrationStatus::EXPIRED,
        ] as $status) {
            $registration = PSERegistration::create([
                'pse_type' => 'PRIVAT',
                'registration_status' => $status,
                'pse_registration_number' => 'X-'.$status->value,
            ]);

            $this->assertFalse($registration->isRegistered());
        }
    }

    public function test_pse_certificate_persists_with_defaults_and_no_generated_serial(): void
    {
        $certificate = PSECertificate::create(['psre_provider' => 'PSrE Indonesia - Badan Sertifikasi']);

        $this->assertSame(PseCertificateStatus::PENDING, $certificate->certificate_status);
        $this->assertSame(VerificationStatus::UNVERIFIED, $certificate->verification_status);
        $this->assertNull($certificate->certificate_number);
        $this->assertDatabaseHas('pse_certificates', [
            'id' => $certificate->id,
            'certificate_number' => null,
            'certificate_status' => PseCertificateStatus::PENDING->value,
        ]);
    }

    public function test_certificate_external_status_is_distinct_from_internal_verification(): void
    {
        $certificate = PSECertificate::create([
            'psre_provider' => 'PSrE Indonesia',
            'certificate_status' => PseCertificateStatus::ACTIVE,
            'verification_status' => VerificationStatus::UNVERIFIED,
        ]);

        $this->assertSame(PseCertificateStatus::ACTIVE, $certificate->certificate_status);
        $this->assertSame(VerificationStatus::UNVERIFIED, $certificate->verification_status);
    }

    public function test_active_certificate_with_past_expiry_is_expired(): void
    {
        $certificate = PSECertificate::create([
            'psre_provider' => 'PSrE Indonesia',
            'certificate_status' => PseCertificateStatus::ACTIVE,
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($certificate->isExpired());
    }

    public function test_active_certificate_with_future_expiry_is_not_expired(): void
    {
        $certificate = PSECertificate::create([
            'psre_provider' => 'PSrE Indonesia',
            'certificate_status' => PseCertificateStatus::ACTIVE,
            'expires_at' => now()->addYears(5)->toDateString(),
        ]);

        $this->assertFalse($certificate->isExpired());
    }

    public function test_expired_status_flags_certificate_as_expired_regardless_of_date(): void
    {
        $certificate = PSECertificate::create([
            'psre_provider' => 'PSrE Indonesia',
            'certificate_status' => PseCertificateStatus::EXPIRED,
            'expires_at' => now()->addYears(5)->toDateString(),
        ]);

        $this->assertTrue($certificate->isExpired());
    }

    public function test_pse_registration_creation_routes_through_module8_audit_logs(): void
    {
        $registration = PSERegistration::create(['pse_type' => 'PRIVAT']);

        (new AuditLogger)->log(null, AuditAction::PSE_REGISTRATION_CREATED, $registration);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'PSERegistration',
            'entity_id' => $registration->id,
            'action' => AuditAction::PSE_REGISTRATION_CREATED->value,
        ]);
    }

    public function test_pse_certificate_creation_routes_through_module8_audit_logs(): void
    {
        $certificate = PSECertificate::create(['psre_provider' => 'PSrE Indonesia']);

        (new AuditLogger)->log(null, AuditAction::PSE_CERTIFICATE_CREATED, $certificate);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'PSECertificate',
            'entity_id' => $certificate->id,
            'action' => AuditAction::PSE_CERTIFICATE_CREATED->value,
        ]);
    }

    public function test_audit_snapshot_captures_registration_state_fields(): void
    {
        $registration = PSERegistration::create(['pse_type' => 'PRIVAT']);

        $previous = (new AuditLogger)->snapshot($registration);

        $registration->update(['registration_status' => PseRegistrationStatus::PENDING]);

        $new = (new AuditLogger)->snapshot($registration);

        $this->assertSame(PseRegistrationStatus::UNREGISTERED->value, $previous['registration_status']);
        $this->assertSame(PseRegistrationStatus::PENDING->value, $new['registration_status']);
        $this->assertSame(PseRegistrationApplicability::UNRESOLVED->value, $new['applicability']);
    }
}
