<?php

declare(strict_types=1);

namespace Tests\Feature\Api\ProcessorAgreement;

use App\Enums\AuditAction;
use App\Enums\HumanReviewStatus;
use App\Enums\HumanReviewType;
use App\Enums\ProcessorRoleStatus;
use App\Models\HumanReviewCase;
use App\Models\ProcessorAgreement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessorAgreementRoleReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_role_review_from_unknown(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/open-role-review", [
            'rule_id' => 'PDP-PROC-001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', HumanReviewStatus::PENDING->value);

        $agreement->refresh();
        $this->assertEquals(ProcessorRoleStatus::REQUIRE_REVIEW->value, $agreement->processor_role_status->value);
        $this->assertNotNull($agreement->human_review_case_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PROCESSOR_AGREEMENT_ROLE_REVIEW_OPENED->value,
            'entity_id' => $agreement->id,
        ]);
    }

    public function test_open_role_review_from_processor(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'processor_role_status' => ProcessorRoleStatus::PROCESSOR,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/open-role-review", [
            'rule_id' => 'PDP-PROC-002',
        ])->assertCreated();

        $agreement->refresh();
        $this->assertEquals(ProcessorRoleStatus::REQUIRE_REVIEW->value, $agreement->processor_role_status->value);
    }

    public function test_open_role_review_from_not_processor(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'processor_role_status' => ProcessorRoleStatus::NOT_PROCESSOR,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/open-role-review", [
            'rule_id' => 'PDP-PROC-003',
        ])->assertCreated();

        $agreement->refresh();
        $this->assertEquals(ProcessorRoleStatus::REQUIRE_REVIEW->value, $agreement->processor_role_status->value);
    }

    public function test_open_role_review_idempotent(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $r1 = $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/open-role-review", [
            'rule_id' => 'PDP-PROC-001',
        ])->assertCreated();

        $r2 = $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/open-role-review", [
            'rule_id' => 'PDP-PROC-001',
        ])->assertCreated();

        $this->assertEquals(
            $r1->json('data.human_review_case_id'),
            $r2->json('data.human_review_case_id'),
        );
        $this->assertDatabaseCount('human_review_cases', 1);
    }

    public function test_open_role_review_creates_case_with_correct_fields(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/open-role-review", [
            'rule_id' => 'PDP-PROC-TEST',
        ])->assertCreated();

        $this->assertDatabaseHas('human_review_cases', [
            'type' => HumanReviewType::PROCESSOR_ROLE->value,
            'rule_id' => 'PDP-PROC-TEST',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
        ]);
    }

    public function test_apply_role_review_processor(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $case = HumanReviewCase::create([
            'type' => HumanReviewType::PROCESSOR_ROLE,
            'rule_id' => 'PDP-PROC-001',
            'trigger' => 'Test',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
            'decision' => 'PROCESSOR',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => Carbon::now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/apply-role-review", [
            'human_review_case_id' => $case->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.processor_role_status', ProcessorRoleStatus::PROCESSOR->value);
    }

    public function test_apply_role_review_not_processor(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $case = HumanReviewCase::create([
            'type' => HumanReviewType::PROCESSOR_ROLE,
            'rule_id' => 'PDP-PROC-001',
            'trigger' => 'Test',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
            'decision' => 'NOT_PROCESSOR',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => Carbon::now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/apply-role-review", [
            'human_review_case_id' => $case->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.processor_role_status', ProcessorRoleStatus::NOT_PROCESSOR->value);
    }

    public function test_apply_role_review_rejected_when_wrong_type(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $case = HumanReviewCase::create([
            'type' => HumanReviewType::TAX,
            'rule_id' => 'TAX-001',
            'trigger' => 'Test',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
            'decision' => 'PROCESSOR',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => Carbon::now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/apply-role-review", [
            'human_review_case_id' => $case->id,
        ])->assertStatus(422);
    }

    public function test_apply_role_review_rejected_when_wrong_subject(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $otherAgreement = ProcessorAgreement::create([
            'counterparty_name' => 'Other Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $case = HumanReviewCase::create([
            'type' => HumanReviewType::PROCESSOR_ROLE,
            'rule_id' => 'PDP-PROC-001',
            'trigger' => 'Test',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $otherAgreement->id,
            'decision' => 'PROCESSOR',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => Carbon::now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/apply-role-review", [
            'human_review_case_id' => $case->id,
        ])->assertStatus(422);
    }

    public function test_apply_role_review_rejected_when_undecided(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $case = HumanReviewCase::create([
            'type' => HumanReviewType::PROCESSOR_ROLE,
            'rule_id' => 'PDP-PROC-001',
            'trigger' => 'Test',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
            'status' => HumanReviewStatus::RECORDED,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/apply-role-review", [
            'human_review_case_id' => $case->id,
        ])->assertStatus(422);
    }

    public function test_apply_role_review_logs_audit(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $case = HumanReviewCase::create([
            'type' => HumanReviewType::PROCESSOR_ROLE,
            'rule_id' => 'PDP-PROC-001',
            'trigger' => 'Test',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
            'decision' => 'PROCESSOR',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => Carbon::now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/apply-role-review", [
            'human_review_case_id' => $case->id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PROCESSOR_AGREEMENT_ROLE_REVIEWED->value,
            'entity_id' => $agreement->id,
        ]);
    }
}
