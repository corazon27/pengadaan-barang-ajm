<?php

declare(strict_types=1);

namespace Tests\Feature\Api\LegalFunction;

use App\Enums\AuditAction;
use App\Enums\HumanReviewStatus;
use App\Enums\HumanReviewType;
use App\Enums\LegalFunctionApplicability;
use App\Enums\LegalFunctionStatus;
use App\Models\HumanReviewCase;
use App\Models\LegalFunctionAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalFunctionApplicabilityReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createAssignmentWithSuperadmin(
        ?LegalFunctionApplicability $applicability = null,
        ?LegalFunctionStatus $status = null,
    ): array {
        $superadmin = User::factory()->superadmin()->create();

        $assignment = LegalFunctionAssignment::create([
            'user_id' => $superadmin->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => $applicability ?? LegalFunctionApplicability::PENDING_LEGAL_REVIEW,
            'status' => $status ?? LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        return ['superadmin' => $superadmin, 'assignment' => $assignment];
    }

    private function createDecidedCase(
        LegalFunctionAssignment $assignment,
        string $decision,
    ): HumanReviewCase {
        return HumanReviewCase::create([
            'type' => HumanReviewType::DPO_APPLICABILITY,
            'rule_id' => 'PDP-DPO-TEST',
            'trigger' => 'Test',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'decision' => $decision,
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => Carbon::now(),
        ]);
    }

    public function test_open_applicability_review_from_pending(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/open-applicability-review", [
            'rule_id' => 'PDP-DPO-001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', HumanReviewStatus::PENDING->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LEGAL_FUNCTION_APPLICABILITY_REVIEW_OPENED->value,
            'entity_id' => $assignment->id,
        ]);
    }

    public function test_open_applicability_review_from_unknown(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin(
            LegalFunctionApplicability::UNKNOWN,
        );

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/open-applicability-review", [
            'rule_id' => 'PDP-DPO-002',
        ])->assertCreated();

        $assignment->refresh();
        $this->assertEquals(LegalFunctionApplicability::LEGAL_REVIEW->value, $assignment->applicability_status->value);
    }

    public function test_open_applicability_review_from_not_required(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin(
            LegalFunctionApplicability::NOT_REQUIRED,
        );

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/open-applicability-review", [
            'rule_id' => 'PDP-DPO-003',
        ])->assertCreated();

        $assignment->refresh();
        $this->assertEquals(LegalFunctionApplicability::LEGAL_REVIEW->value, $assignment->applicability_status->value);
    }

    public function test_open_applicability_review_idempotent(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();

        $this->actingAs($superadmin);

        $response1 = $this->patchJson("/api/v1/legal-functions/{$assignment->id}/open-applicability-review", [
            'rule_id' => 'PDP-DPO-001',
        ])->assertCreated();

        $response2 = $this->patchJson("/api/v1/legal-functions/{$assignment->id}/open-applicability-review", [
            'rule_id' => 'PDP-DPO-001',
        ])->assertCreated();

        $this->assertEquals(
            $response1->json('data.human_review_case_id'),
            $response2->json('data.human_review_case_id'),
        );

        $this->assertDatabaseCount('human_review_cases', 1);
    }

    public function test_open_applicability_review_sets_human_review_case_id(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();

        $this->actingAs($superadmin);

        $response = $this->patchJson("/api/v1/legal-functions/{$assignment->id}/open-applicability-review", [
            'rule_id' => 'PDP-DPO-001',
        ])->assertCreated();

        $caseId = $response->json('data.human_review_case_id');

        $assignment->refresh();
        $this->assertEquals($caseId, $assignment->human_review_case_id);
    }

    public function test_open_applicability_review_transitions_to_legal_review(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/open-applicability-review", [
            'rule_id' => 'PDP-DPO-001',
        ])->assertCreated();

        $assignment->refresh();
        $this->assertEquals(LegalFunctionApplicability::LEGAL_REVIEW->value, $assignment->applicability_status->value);
    }

    public function test_open_review_creates_case_with_correct_fields(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/open-applicability-review", [
            'rule_id' => 'PDP-DPO-TEST',
        ])->assertCreated();

        $this->assertDatabaseHas('human_review_cases', [
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'rule_id' => 'PDP-DPO-TEST',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
        ]);
    }

    public function test_apply_review_required(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();
        $case = $this->createDecidedCase($assignment, 'REQUIRED');

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/apply-applicability-review", [
            'human_review_case_id' => $case->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.applicability_status', LegalFunctionApplicability::REQUIRED->value);
    }

    public function test_apply_review_not_required(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();
        $case = $this->createDecidedCase($assignment, 'NOT_REQUIRED');

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/apply-applicability-review", [
            'human_review_case_id' => $case->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.applicability_status', LegalFunctionApplicability::NOT_REQUIRED->value);
    }

    public function test_apply_review_rejected_when_case_not_dpo_type(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();

        $case = HumanReviewCase::create([
            'type' => HumanReviewType::TAX,
            'rule_id' => 'TAX-001',
            'trigger' => 'Test',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'decision' => 'REQUIRED',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => Carbon::now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/apply-applicability-review", [
            'human_review_case_id' => $case->id,
        ])->assertStatus(422);
    }

    public function test_apply_review_rejected_when_wrong_subject(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();
        $otherAssignment = LegalFunctionAssignment::create([
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $case = HumanReviewCase::create([
            'type' => HumanReviewType::DPO_APPLICABILITY,
            'rule_id' => 'PDP-DPO-001',
            'trigger' => 'Test',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $otherAssignment->id,
            'decision' => 'REQUIRED',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => Carbon::now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/apply-applicability-review", [
            'human_review_case_id' => $case->id,
        ])->assertStatus(422);
    }

    public function test_apply_review_rejected_when_case_undecided(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();

        $case = HumanReviewCase::create([
            'type' => HumanReviewType::DPO_APPLICABILITY,
            'rule_id' => 'PDP-DPO-001',
            'trigger' => 'Test',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'status' => HumanReviewStatus::RECORDED,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/apply-applicability-review", [
            'human_review_case_id' => $case->id,
        ])->assertStatus(422);
    }

    public function test_apply_review_logs_audit_action(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();
        $case = $this->createDecidedCase($assignment, 'REQUIRED');

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/apply-applicability-review", [
            'human_review_case_id' => $case->id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LEGAL_FUNCTION_APPLICABILITY_REVIEWED->value,
            'entity_id' => $assignment->id,
        ]);
    }

    public function test_open_review_logs_audit_action(): void
    {
        ['superadmin' => $superadmin, 'assignment' => $assignment] = $this->createAssignmentWithSuperadmin();

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/open-applicability-review", [
            'rule_id' => 'PDP-DPO-001',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LEGAL_FUNCTION_APPLICABILITY_REVIEW_OPENED->value,
            'entity_id' => $assignment->id,
        ]);
    }
}
