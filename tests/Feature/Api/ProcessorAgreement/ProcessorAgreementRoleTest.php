<?php

declare(strict_types=1);

namespace Tests\Feature\Api\ProcessorAgreement;

use App\Enums\AgreementStatus;
use App\Enums\AuditAction;
use App\Enums\HumanReviewStatus;
use App\Enums\HumanReviewType;
use App\Enums\ProcessorRoleStatus;
use App\Models\HumanReviewCase;
use App\Models\ProcessorAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessorAgreementRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_determine_role_processor(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'effective_from' => '2026-01-01',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/determine-role", [
            'processor_role_status' => 'PROCESSOR',
        ])
            ->assertOk()
            ->assertJsonPath('data.processor_role_status', ProcessorRoleStatus::PROCESSOR->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PROCESSOR_AGREEMENT_ROLE_DETERMINED->value,
            'entity_id' => $agreement->id,
        ]);
    }

    public function test_determine_role_not_processor(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'effective_from' => '2026-01-01',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/determine-role", [
            'processor_role_status' => 'NOT_PROCESSOR',
        ])
            ->assertOk()
            ->assertJsonPath('data.processor_role_status', ProcessorRoleStatus::NOT_PROCESSOR->value);
    }

    public function test_determine_role_rejected_when_not_unknown(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'effective_from' => '2026-01-01',
            'processor_role_status' => ProcessorRoleStatus::PROCESSOR,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/determine-role", [
            'processor_role_status' => 'NOT_PROCESSOR',
        ])->assertStatus(422);
    }

    public function test_determine_role_rejected_without_evidence_reference(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_verified' => true,
            'effective_from' => '2026-01-01',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/determine-role", [
            'processor_role_status' => 'PROCESSOR',
        ])->assertStatus(422);
    }

    public function test_determine_role_rejected_without_effective_from(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/determine-role", [
            'processor_role_status' => 'PROCESSOR',
        ])->assertStatus(422);
    }

    public function test_determine_role_rejected_when_evidence_not_verified(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => false,
            'effective_from' => '2026-01-01',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/determine-role", [
            'processor_role_status' => 'PROCESSOR',
        ])->assertStatus(422);
    }

    public function test_determine_role_rejected_when_unresolved_review_exists(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'effective_from' => '2026-01-01',
            'actor_user_id' => $superadmin->id,
        ]);

        HumanReviewCase::create([
            'type' => HumanReviewType::PROCESSOR_ROLE,
            'rule_id' => 'PDP-PROC-001',
            'trigger' => 'Test',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/determine-role", [
            'processor_role_status' => 'PROCESSOR',
        ])->assertStatus(422);
    }

    public function test_determine_role_after_resolved_review_succeeds(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_reference' => 'DOC-001',
            'evidence_verified' => true,
            'effective_from' => '2026-01-01',
            'actor_user_id' => $superadmin->id,
        ]);

        HumanReviewCase::create([
            'type' => HumanReviewType::PROCESSOR_ROLE,
            'rule_id' => 'PDP-PROC-001',
            'trigger' => 'Test',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
            'status' => HumanReviewStatus::APPROVED,
            'decision' => 'PROCESSOR',
            'decided_at' => now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/determine-role", [
            'processor_role_status' => 'PROCESSOR',
        ])->assertOk();
    }

    public function test_update_role_status_excluded_from_request(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'processor_role_status' => ProcessorRoleStatus::UNKNOWN,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'processor_role_status' => 'PROCESSOR',
        ])->assertOk();

        $agreement->refresh();
        $this->assertEquals(ProcessorRoleStatus::UNKNOWN->value, $agreement->processor_role_status->value);
    }

    public function test_update_agreement_status_excluded_from_request(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'agreement_status' => 'ACTIVE',
        ])->assertOk();

        $agreement->refresh();
        $this->assertEquals(AgreementStatus::DRAFT->value, $agreement->agreement_status->value);
    }

    public function test_update_evidence_verified_excluded_from_request(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'evidence_verified' => false,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}", [
            'evidence_verified' => true,
        ])->assertOk();

        $agreement->refresh();
        $this->assertFalse($agreement->evidence_verified);
    }
}
