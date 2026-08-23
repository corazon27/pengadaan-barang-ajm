<?php

declare(strict_types=1);

namespace Tests\Feature\Api\ProcessorAgreement;

use App\Enums\AgreementStatus;
use App\Enums\AuditAction;
use App\Enums\ProcessorRoleStatus;
use App\Models\ProcessorAgreement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessorAgreementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_processor_agreement(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin);

        $this->postJson('/api/v1/processor-agreements', [
            'counterparty_name' => 'Cloud Provider Inc',
            'counterparty_identifier' => ['npwp' => '1234567890'],
            'data_categories' => ['personal_data', 'sensitive_data'],
            'processing_purposes' => ['cloud_storage'],
            'legal_basis' => 'consent',
            'effective_from' => '2026-01-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.counterparty_name', 'Cloud Provider Inc')
            ->assertJsonPath('data.processor_role_status', ProcessorRoleStatus::UNKNOWN->value)
            ->assertJsonPath('data.agreement_status', AgreementStatus::DRAFT->value)
            ->assertJsonPath('data.evidence_verified', false);

        $this->assertDatabaseHas('processor_agreements', [
            'counterparty_name' => 'Cloud Provider Inc',
            'processor_role_status' => ProcessorRoleStatus::UNKNOWN->value,
            'agreement_status' => AgreementStatus::DRAFT->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PROCESSOR_AGREEMENT_CREATED->value,
            'entity_type' => 'ProcessorAgreement',
        ]);
    }

    public function test_draft_to_active_transition(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/agreement-status", [
            'agreement_status' => 'ACTIVE',
            'reason' => 'Agreement finalized',
        ])
            ->assertOk()
            ->assertJsonPath('data.agreement_status', AgreementStatus::ACTIVE->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PROCESSOR_AGREEMENT_STATUS_CHANGED->value,
            'entity_id' => $agreement->id,
        ]);
    }

    public function test_active_to_suspended_transition(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'agreement_status' => AgreementStatus::ACTIVE,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/agreement-status", [
            'agreement_status' => 'SUSPENDED',
            'reason' => 'Under review',
        ])
            ->assertOk()
            ->assertJsonPath('data.agreement_status', AgreementStatus::SUSPENDED->value);
    }

    public function test_suspended_to_active_transition(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'agreement_status' => AgreementStatus::SUSPENDED,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/agreement-status", [
            'agreement_status' => 'ACTIVE',
        ])->assertOk();

        $agreement->refresh();
        $this->assertEquals(AgreementStatus::ACTIVE->value, $agreement->agreement_status->value);
    }

    public function test_active_to_expired_transition(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'agreement_status' => AgreementStatus::ACTIVE,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/agreement-status", [
            'agreement_status' => 'EXPIRED',
        ])
            ->assertOk()
            ->assertJsonPath('data.agreement_status', AgreementStatus::EXPIRED->value);
    }

    public function test_active_to_terminated_transition(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'agreement_status' => AgreementStatus::ACTIVE,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/agreement-status", [
            'agreement_status' => 'TERMINATED',
            'reason' => 'Contract breach',
        ])
            ->assertOk()
            ->assertJsonPath('data.agreement_status', AgreementStatus::TERMINATED->value);
    }

    public function test_draft_to_terminated_transition(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'agreement_status' => AgreementStatus::DRAFT,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/agreement-status", [
            'agreement_status' => 'TERMINATED',
        ])
            ->assertOk()
            ->assertJsonPath('data.agreement_status', AgreementStatus::TERMINATED->value);
    }

    public function test_invalid_transition_rejected(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'agreement_status' => AgreementStatus::DRAFT,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/agreement-status", [
            'agreement_status' => 'SUSPENDED',
        ])->assertStatus(422);
    }

    public function test_helpers_return_correct_values(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $activeNoExpiry = ProcessorAgreement::create([
            'counterparty_name' => 'Active No Expiry',
            'agreement_status' => AgreementStatus::ACTIVE,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->assertTrue($activeNoExpiry->isActiveAgreement());
        $this->assertFalse($activeNoExpiry->isRoleDetermined());

        $activeExpired = ProcessorAgreement::create([
            'counterparty_name' => 'Active Expired',
            'agreement_status' => AgreementStatus::ACTIVE,
            'effective_until' => Carbon::now()->subDay(),
            'actor_user_id' => $superadmin->id,
        ]);

        $this->assertFalse($activeExpired->isActiveAgreement());

        $draft = ProcessorAgreement::create([
            'counterparty_name' => 'Draft',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->assertFalse($draft->isActiveAgreement());

        $withProcessorRole = ProcessorAgreement::create([
            'counterparty_name' => 'With Role',
            'agreement_status' => AgreementStatus::ACTIVE,
            'processor_role_status' => ProcessorRoleStatus::PROCESSOR,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->assertTrue($withProcessorRole->isRoleDetermined());
        $this->assertFalse($withProcessorRole->isReviewRequired());
    }

    public function test_index_returns_paginated_results(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/processor-agreements')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_single_agreement(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->getJson("/api/v1/processor-agreements/{$agreement->id}")
            ->assertOk()
            ->assertJsonPath('data.counterparty_name', 'Test Corp');
    }
}
