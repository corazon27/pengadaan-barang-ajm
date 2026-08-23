<?php

declare(strict_types=1);

namespace Tests\Feature\Api\LegalFunction;

use App\Enums\AuditAction;
use App\Enums\LegalFunctionApplicability;
use App\Enums\LegalFunctionStatus;
use App\Models\LegalFunctionAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalFunctionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_legal_function(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->postJson('/api/v1/legal-functions', [
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'effective_from' => '2026-01-01',
            'user_id' => $superadmin->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', LegalFunctionStatus::INACTIVE->value)
            ->assertJsonPath('data.applicability_status', LegalFunctionApplicability::PENDING_LEGAL_REVIEW->value);

        $this->assertDatabaseHas('legal_function_assignments', [
            'function_code' => 'DPO',
            'status' => LegalFunctionStatus::INACTIVE->value,
            'applicability_status' => LegalFunctionApplicability::PENDING_LEGAL_REVIEW->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LEGAL_FUNCTION_CREATED->value,
            'entity_type' => 'LegalFunctionAssignment',
        ]);
    }

    public function test_superadmin_can_activate_legal_function(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $assignment = LegalFunctionAssignment::create([
            'user_id' => $superadmin->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => LegalFunctionApplicability::REQUIRED,
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', LegalFunctionStatus::ACTIVE->value);

        $this->assertDatabaseHas('legal_function_assignments', [
            'id' => $assignment->id,
            'status' => LegalFunctionStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LEGAL_FUNCTION_ACTIVATED->value,
            'entity_id' => $assignment->id,
        ]);
    }

    public function test_superadmin_can_suspend_and_terminate(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $assignment = LegalFunctionAssignment::create([
            'user_id' => $superadmin->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => LegalFunctionApplicability::REQUIRED,
            'status' => LegalFunctionStatus::ACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/suspend", [
            'reason' => 'Temporary suspension',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', LegalFunctionStatus::SUSPENDED->value);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/terminate", [
            'reason' => 'No longer needed',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', LegalFunctionStatus::TERMINATED->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LEGAL_FUNCTION_SUSPENDED->value,
            'entity_id' => $assignment->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LEGAL_FUNCTION_TERMINATED->value,
            'entity_id' => $assignment->id,
        ]);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $assignment = LegalFunctionAssignment::create([
            'user_id' => $superadmin->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/suspend", [
            'reason' => 'Test',
        ])->assertStatus(422);
    }

    public function test_helpers_return_correct_values(): void
    {
        $active = LegalFunctionAssignment::create([
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => LegalFunctionApplicability::REQUIRED,
            'status' => LegalFunctionStatus::ACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $inactive = LegalFunctionAssignment::create([
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $expired = LegalFunctionAssignment::create([
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => LegalFunctionApplicability::REQUIRED,
            'status' => LegalFunctionStatus::ACTIVE,
            'effective_from' => '2020-01-01',
            'effective_until' => '2020-12-31',
        ]);

        $this->assertTrue($active->isActive());
        $this->assertTrue($active->isApplicable());
        $this->assertFalse($active->isExpired());

        $this->assertFalse($inactive->isActive());

        $this->assertTrue($expired->isActive());
        $this->assertTrue($expired->isExpired());
    }

    public function test_unauthorized_user_cannot_create_legal_function(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $this->actingAs($user);

        $this->postJson('/api/v1/legal-functions', [
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'effective_from' => '2026-01-01',
        ])->assertForbidden();
    }

    public function test_index_returns_paginated_results(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        LegalFunctionAssignment::create([
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/legal-functions')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_single_assignment(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $assignment = LegalFunctionAssignment::create([
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($superadmin);

        $this->getJson("/api/v1/legal-functions/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('data.function_code', 'DPO');
    }

    public function test_update_modifies_metadata_fields(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $assignment = LegalFunctionAssignment::create([
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}", [
            'function_category' => 'PRIVACY',
            'scope' => 'Company-wide',
        ])
            ->assertOk()
            ->assertJsonPath('data.function_category', 'PRIVACY')
            ->assertJsonPath('data.scope', 'Company-wide');

        $this->assertDatabaseHas('legal_function_assignments', [
            'id' => $assignment->id,
            'function_category' => 'PRIVACY',
            'scope' => 'Company-wide',
        ]);
    }

    public function test_update_cannot_change_excluded_fields(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $assignment = LegalFunctionAssignment::create([
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'status' => LegalFunctionStatus::INACTIVE,
            'applicability_status' => LegalFunctionApplicability::PENDING_LEGAL_REVIEW,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}", [
            'status' => 'ACTIVE',
            'applicability_status' => 'REQUIRED',
        ])->assertOk();

        $assignment->refresh();
        $this->assertEquals(LegalFunctionStatus::INACTIVE->value, $assignment->status->value);
        $this->assertEquals(LegalFunctionApplicability::PENDING_LEGAL_REVIEW->value, $assignment->applicability_status->value);
    }
}
