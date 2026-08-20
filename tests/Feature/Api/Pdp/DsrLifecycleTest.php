<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Pdp;

use App\Enums\AuditAction;
use App\Enums\DsrStatus;
use App\Models\DataSubjectRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DsrLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_dsr(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJson('/api/v1/dsr', [
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
            'request_input' => ['target' => 'profile_data'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.right_code', 'PDP-RIGHT-001')
            ->assertJsonPath('data.status', DsrStatus::RECEIVED->value)
            ->assertJsonPath('data.identity_verification_status', 'VERIFIED');

        $this->assertDatabaseHas('data_subject_requests', [
            'right_code' => 'PDP-RIGHT-001',
            'status' => DsrStatus::RECEIVED->value,
        ]);
    }

    public function test_superadmin_can_list_dsrs(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        DataSubjectRequest::create([
            'subject_user_id' => User::factory()->create()->id,
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
        ]);

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/dsr')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_regular_user_cannot_list_all_dsrs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->getJson('/api/v1/dsr')
            ->assertForbidden();
    }

    public function test_subject_can_view_own_dsr(): void
    {
        $user = User::factory()->create();

        $dsr = DataSubjectRequest::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
        ]);

        $this->actingAs($user);

        $this->getJson("/api/v1/dsr/{$dsr->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $dsr->id);
    }

    public function test_regular_user_cannot_view_other_user_dsr(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $dsr = DataSubjectRequest::create([
            'subject_user_id' => $other->id,
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
        ]);

        $this->actingAs($user);

        $this->getJson("/api/v1/dsr/{$dsr->id}")
            ->assertForbidden();
    }

    public function test_superadmin_can_verify_identity(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $dsr = DataSubjectRequest::create([
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/dsr/{$dsr->id}/verify-identity", [
            'identity_verification_status' => 'VERIFIED',
        ])
            ->assertOk()
            ->assertJsonPath('data.identity_verification_status', 'VERIFIED')
            ->assertJsonPath('data.status', DsrStatus::IDENTITY_VERIFIED->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DSR_IDENTITY_VERIFIED->value,
            'entity_type' => 'DataSubjectRequest',
        ]);
    }

    public function test_superadmin_can_classify_right(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $dsr = DataSubjectRequest::create([
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/dsr/{$dsr->id}/classify-right", [
            'applicability_status' => 'CONFIRMED',
        ])
            ->assertOk()
            ->assertJsonPath('data.applicability_status', 'CONFIRMED');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DSR_RIGHT_CLASSIFIED->value,
            'entity_type' => 'DataSubjectRequest',
        ]);
    }

    public function test_superadmin_can_fulfill_dsr(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $dsr = DataSubjectRequest::create([
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
            'status' => DsrStatus::PROCESSING,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/dsr/{$dsr->id}/fulfill", [
            'decision_notes' => 'Data exported successfully',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DsrStatus::FULFILLED->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DSR_FULFILLED->value,
            'entity_type' => 'DataSubjectRequest',
        ]);
    }

    public function test_superadmin_can_reject_dsr(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $dsr = DataSubjectRequest::create([
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
            'status' => DsrStatus::PROCESSING,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/dsr/{$dsr->id}/reject", [
            'decision_notes' => 'Right not applicable',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DsrStatus::REJECTED->value);
    }

    public function test_superadmin_can_close_dsr(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $dsr = DataSubjectRequest::create([
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
            'status' => DsrStatus::FULFILLED,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/dsr/{$dsr->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', DsrStatus::CLOSED->value);
    }

    public function test_regular_user_cannot_fulfill_dsr(): void
    {
        $user = User::factory()->create();

        $dsr = DataSubjectRequest::create([
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
        ]);

        $this->actingAs($user);

        $this->patchJson("/api/v1/dsr/{$dsr->id}/fulfill")
            ->assertForbidden();
    }

    public function test_dsr_creation_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJson('/api/v1/dsr', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_type', 'right_code', 'channel']);
    }

    public function test_dsr_store_creates_audit_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJson('/api/v1/dsr', [
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-003',
            'channel' => 'EMAIL',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DSR_CREATED->value,
            'entity_type' => 'DataSubjectRequest',
        ]);
    }

    public function test_superadmin_can_resolve_lawful_basis(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $dsr = DataSubjectRequest::create([
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/dsr/{$dsr->id}/resolve-lawful-basis", [
            'processing_class' => 'analytics',
        ])
            ->assertOk()
            ->assertJsonPath('data.processing_lawful_basis_evaluated.basis', 'LEGITIMATE_INTEREST')
            ->assertJsonPath('data.processing_lawful_basis_evaluated.rule_id', 'RULE-DI-005')
            ->assertJsonPath('data.processing_lawful_basis_evaluated.requires_review', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DSR_LAWFUL_BASIS_RESOLVED->value,
            'entity_type' => 'DataSubjectRequest',
        ]);
    }

    public function test_superadmin_can_open_human_review(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $dsr = DataSubjectRequest::create([
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-001',
            'channel' => 'WEB',
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/dsr/{$dsr->id}/open-human-review", [
            'decision_type' => 'PDP',
            'rule_id' => 'RULE-DI-003',
            'notes' => 'Requires manual review for procurement data',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DsrStatus::REVIEW_REQUIRED->value)
            ->assertJsonStructure(['data' => ['human_review_case_id']]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DSR_HUMAN_REVIEW_OPENED->value,
            'entity_type' => 'DataSubjectRequest',
        ]);
    }
}
