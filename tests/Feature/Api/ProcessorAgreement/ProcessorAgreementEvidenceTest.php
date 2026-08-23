<?php

declare(strict_types=1);

namespace Tests\Feature\Api\ProcessorAgreement;

use App\Enums\AuditAction;
use App\Enums\ProcessorRoleStatus;
use App\Models\ProcessorAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessorAgreementEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_evidence_sets_verified_true(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/verify-evidence", [
            'factual_basis' => 'Contract review',
        ])
            ->assertOk()
            ->assertJsonPath('data.evidence_verified', true);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PROCESSOR_AGREEMENT_EVIDENCE_VERIFIED->value,
            'entity_id' => $agreement->id,
        ]);
    }

    public function test_verify_evidence_rejected_without_reference(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/verify-evidence", [
            'factual_basis' => 'Contract review',
        ])->assertStatus(422);
    }

    public function test_verify_evidence_rejected_when_already_verified(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/verify-evidence", [
            'factual_basis' => 'Re-verify',
        ])->assertStatus(422);
    }

    public function test_update_evidence_reference_invalidates_verification(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'processor_role_status' => ProcessorRoleStatus::PROCESSOR,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'evidence_reference' => 'DOC-002',
        ])->assertOk();

        $agreement->refresh();
        $this->assertFalse($agreement->evidence_verified);
        $this->assertNull($agreement->evidence_verified_at);
    }

    public function test_evidence_invalidation_resets_role_status_to_require_review(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'processor_role_status' => ProcessorRoleStatus::PROCESSOR,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'evidence_reference' => 'DOC-002',
        ])->assertOk();

        $agreement->refresh();
        $this->assertEquals(ProcessorRoleStatus::REQUIRE_REVIEW->value, $agreement->processor_role_status->value);
    }

    public function test_evidence_invalidation_does_not_reset_unknown_role(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'processor_role_status' => ProcessorRoleStatus::UNKNOWN,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'evidence_reference' => 'DOC-002',
        ])->assertOk();

        $agreement->refresh();
        $this->assertEquals(ProcessorRoleStatus::UNKNOWN->value, $agreement->processor_role_status->value);
    }

    public function test_effective_until_does_not_invalidate_evidence(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'processor_role_status' => ProcessorRoleStatus::PROCESSOR,
            'effective_until' => '2026-12-31',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'effective_until' => '2027-12-31',
        ])->assertOk();

        $agreement->refresh();
        $this->assertTrue($agreement->evidence_verified);
        $this->assertEquals(ProcessorRoleStatus::PROCESSOR->value, $agreement->processor_role_status->value);
    }

    public function test_update_counterparty_identifier_invalidates_evidence(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'counterparty_identifier' => ['npwp' => '111'],
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'processor_role_status' => ProcessorRoleStatus::NOT_PROCESSOR,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'counterparty_identifier' => ['npwp' => '222'],
        ])->assertOk();

        $agreement->refresh();
        $this->assertFalse($agreement->evidence_verified);
        $this->assertEquals(ProcessorRoleStatus::REQUIRE_REVIEW->value, $agreement->processor_role_status->value);
    }

    public function test_update_effective_from_invalidates_evidence(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'processor_role_status' => ProcessorRoleStatus::PROCESSOR,
            'effective_from' => '2026-01-01',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'effective_from' => '2026-06-01',
        ])->assertOk();

        $agreement->refresh();
        $this->assertFalse($agreement->evidence_verified);
        $this->assertEquals(ProcessorRoleStatus::REQUIRE_REVIEW->value, $agreement->processor_role_status->value);
    }

    public function test_no_invalidation_when_evidence_not_verified(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => false,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'evidence_reference' => 'DOC-002',
        ])->assertOk();

        $agreement->refresh();
        $this->assertFalse($agreement->evidence_verified);
    }
}
