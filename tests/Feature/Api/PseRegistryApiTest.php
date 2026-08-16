<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\AuditAction;
use App\Enums\PseCertificateStatus;
use App\Enums\PseRegistrationApplicability;
use App\Enums\PseRegistrationStatus;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\PSECertificate;
use App\Models\PSERegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3B.1 — PSE registry API surface. Registry governance records are
 * superadmin-only (RBAC role gate; no legal-function inference). Every create
 * and update must route through the existing Module 8 audit_logs substrate.
 */
class PseRegistryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_pse_registration(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->postJson('/api/v1/pse/registrations', [
            'pse_type' => 'PRIVAT',
            'pse_registration_number' => 'RE-2026-0001',
            'registered_at' => '2026-03-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.pse_type', 'PRIVAT')
            ->assertJsonPath('data.registration_status', PseRegistrationStatus::UNREGISTERED->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PSE_REGISTRATION_CREATED->value,
            'entity_type' => 'PSERegistration',
        ]);
    }

    public function test_superadmin_can_record_registered_status_with_evidence(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->postJson('/api/v1/pse/registrations', [
            'pse_type' => 'PRIVAT',
            'pse_registration_number' => '012345.0001.2026',
            'registered_at' => '2026-03-01',
            'maintenance_due_at' => '2027-03-01',
            'registration_status' => PseRegistrationStatus::REGISTERED->value,
            'applicability' => PseRegistrationApplicability::CONFIRMED->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_registered', true)
            ->assertJsonPath('data.applicability', PseRegistrationApplicability::CONFIRMED->value);
    }

    public function test_superadmin_can_list_and_show_pse_registrations(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        PSERegistration::create(['pse_type' => 'PRIVAT']);
        PSERegistration::create(['pse_type' => 'PRIVAT']);

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/pse/registrations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $id = PSERegistration::query()->first()->id;

        $this->getJson("/api/v1/pse/registrations/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_superadmin_can_update_pse_registration_with_audit_trail(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $registration = PSERegistration::create(['pse_type' => 'PRIVAT']);

        $this->actingAs($superadmin);

        $this->putJson("/api/v1/pse/registrations/{$registration->id}", [
            'registration_status' => PseRegistrationStatus::PENDING->value,
            'pse_registration_number' => 'RE-2026-0042',
        ])
            ->assertOk()
            ->assertJsonPath('data.registration_status', PseRegistrationStatus::PENDING->value);

        $log = AuditLog::where('action', AuditAction::PSE_REGISTRATION_UPDATED->value)
            ->where('entity_id', $registration->id)
            ->firstOrFail();

        $this->assertSame(PseRegistrationStatus::UNREGISTERED->value, $log->previous_state['registration_status']);
        $this->assertSame(PseRegistrationStatus::PENDING->value, $log->new_state['registration_status']);
    }

    public function test_create_pse_registration_requires_pse_type(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->postJson('/api/v1/pse/registrations', [])
            ->assertUnprocessable();
    }

    public function test_superadmin_can_create_pse_certificate(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->postJson('/api/v1/pse/certificates', [
            'psre_provider' => 'PSrE Indonesia',
            'certificate_number' => 'SE-2026-0042',
            'issued_at' => '2026-01-01',
            'expires_at' => '2029-01-01',
            'certificate_status' => PseCertificateStatus::ACTIVE->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.certificate_status', PseCertificateStatus::ACTIVE->value)
            ->assertJsonPath('data.verification_status', VerificationStatus::UNVERIFIED->value)
            ->assertJsonPath('data.is_expired', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PSE_CERTIFICATE_CREATED->value,
            'entity_type' => 'PSECertificate',
        ]);
    }

    public function test_superadmin_can_update_pse_certificate_with_audit_trail(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $certificate = PSECertificate::create(['psre_provider' => 'PSrE Indonesia']);

        $this->actingAs($superadmin);

        $this->putJson("/api/v1/pse/certificates/{$certificate->id}", [
            'certificate_status' => PseCertificateStatus::ACTIVE->value,
            'expires_at' => now()->addYears(3)->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.certificate_status', PseCertificateStatus::ACTIVE->value);

        $log = AuditLog::where('action', AuditAction::PSE_CERTIFICATE_UPDATED->value)
            ->where('entity_id', $certificate->id)
            ->firstOrFail();

        $this->assertSame(PseCertificateStatus::PENDING->value, $log->previous_state['certificate_status']);
        $this->assertSame(PseCertificateStatus::ACTIVE->value, $log->new_state['certificate_status']);
        $this->assertSame(VerificationStatus::UNVERIFIED->value, $log->new_state['verification_status']);
    }

    public function test_certificate_expiry_must_follow_issuance(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->postJson('/api/v1/pse/certificates', [
            'psre_provider' => 'PSrE Indonesia',
            'issued_at' => '2026-06-01',
            'expires_at' => '2026-01-01',
        ])->assertUnprocessable();
    }

    public function test_buyer_cannot_access_pse_registries(): void
    {
        $buyer = User::factory()->buyerB2b()->create();

        $this->actingAs($buyer);

        $this->getJson('/api/v1/pse/registrations')->assertForbidden();
        $this->postJson('/api/v1/pse/registrations', ['pse_type' => 'PRIVAT'])->assertForbidden();
        $this->getJson('/api/v1/pse/certificates')->assertForbidden();
        $this->postJson('/api/v1/pse/certificates', ['psre_provider' => 'PSrE Indonesia'])->assertForbidden();
    }

    public function test_pse_registries_require_authentication(): void
    {
        $this->getJson('/api/v1/pse/registrations')->assertUnauthorized();
        $this->getJson('/api/v1/pse/certificates')->assertUnauthorized();
    }
}
