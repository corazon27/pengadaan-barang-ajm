<?php

declare(strict_types=1);

namespace Tests\Feature\Api\LegalFunction;

use App\Enums\LegalFunctionApplicability;
use App\Enums\LegalFunctionStatus;
use App\Models\LegalFunctionAssignment;
use App\Models\User;
use App\Services\LegalFunctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalFunctionInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_activate_rejected_when_applicability_not_required(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $assignment = LegalFunctionAssignment::create([
            'user_id' => $superadmin->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => LegalFunctionApplicability::PENDING_LEGAL_REVIEW,
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);
        $this->actingAs($superadmin);
        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/activate")->assertStatus(422);
    }

    public function test_activate_rejected_when_user_id_null(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $assignment = LegalFunctionAssignment::create([
            'user_id' => null,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => LegalFunctionApplicability::REQUIRED,
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);
        $this->actingAs($superadmin);
        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/activate")->assertStatus(422);
    }

    public function test_active_requires_user_id_not_null(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $lf = LegalFunctionAssignment::create([
            'user_id' => $superadmin->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => LegalFunctionApplicability::REQUIRED,
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);
        $this->actingAs($superadmin);
        $this->patchJson("/api/v1/legal-functions/{$lf->id}/activate")->assertOk();
        $lf->refresh();
        $this->assertNotNull($lf->user_id);
    }

    public function test_active_requires_required_applicability(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $lf = LegalFunctionAssignment::create([
            'user_id' => $superadmin->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => LegalFunctionApplicability::REQUIRED,
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);
        $this->actingAs($superadmin);
        $this->patchJson("/api/v1/legal-functions/{$lf->id}/activate")->assertOk();
        $lf->refresh();
        $this->assertEquals(LegalFunctionApplicability::REQUIRED->value, $lf->applicability_status->value);
    }

    public function test_superadmin_not_auto_assigned_as_dpo(): void
    {
        User::factory()->superadmin()->create();
        $this->assertDatabaseEmpty('legal_function_assignments');
    }

    public function test_applicability_status_not_directly_settable_on_create(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin);
        $this->postJson('/api/v1/legal-functions', [
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'effective_from' => '2026-01-01',
        ])->assertCreated();
        $this->assertDatabaseHas('legal_function_assignments', [
            'function_code' => 'DPO',
            'applicability_status' => LegalFunctionApplicability::PENDING_LEGAL_REVIEW->value,
        ]);
    }

    public function test_update_rejected_when_active_and_user_id_null(): void
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
        $this->patchJson("/api/v1/legal-functions/{$assignment->id}", [
            'user_id' => null,
        ])->assertStatus(422);
    }

    public function test_update_applicability_excluded_from_request(): void
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
        $this->patchJson("/api/v1/legal-functions/{$assignment->id}", [
            'applicability_status' => 'NOT_REQUIRED',
        ])->assertOk();
        $assignment->refresh();
        $this->assertEquals(LegalFunctionApplicability::REQUIRED->value, $assignment->applicability_status->value);
    }

    public function test_update_succeeds_with_safe_metadata_fields(): void
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
        $this->patchJson("/api/v1/legal-functions/{$assignment->id}", [
            'function_category' => 'PRIVACY',
            'scope' => 'Updated scope',
        ])
            ->assertOk()
            ->assertJsonPath('data.function_category', 'PRIVACY')
            ->assertJsonPath('data.scope', 'Updated scope');
    }

    public function test_update_allows_function_code_when_inactive(): void
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
        $this->patchJson("/api/v1/legal-functions/{$assignment->id}", [
            'function_code' => 'DPO',
        ])->assertOk();
        $assignment->refresh();
        $this->assertEquals('DPO', $assignment->function_code);
    }

    public function test_update_rejected_when_terminated(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $assignment = LegalFunctionAssignment::create([
            'user_id' => $superadmin->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'status' => LegalFunctionStatus::TERMINATED,
            'effective_from' => '2026-01-01',
        ]);
        $this->actingAs($superadmin);
        $this->patchJson("/api/v1/legal-functions/{$assignment->id}", [
            'scope' => 'Updated',
        ])->assertStatus(422);
    }

    public function test_update_status_excluded_from_request(): void
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
        $this->patchJson("/api/v1/legal-functions/{$assignment->id}", [
            'status' => 'SUSPENDED',
        ])->assertOk();
        $assignment->refresh();
        $this->assertEquals(LegalFunctionStatus::ACTIVE->value, $assignment->status->value);
    }

    public function test_update_active_function_code_rejected_via_service(): void
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
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot change function_code');
        app(LegalFunctionService::class)->update($superadmin, $assignment, ['function_code' => 'DPO_V2']);
    }
}
