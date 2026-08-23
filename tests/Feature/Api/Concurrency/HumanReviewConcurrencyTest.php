<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Concurrency;

use App\Enums\HumanReviewStatus;
use App\Enums\HumanReviewType;
use App\Enums\LegalFunctionApplicability;
use App\Enums\LegalFunctionStatus;
use App\Models\HumanReviewCase;
use App\Models\LegalFunctionAssignment;
use App\Models\ProcessorAgreement;
use App\Models\User;
use App\Services\LegalFunctionService;
use App\Services\ProcessorAgreementService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HumanReviewConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function createDpoAssignment(): LegalFunctionAssignment
    {
        $superadmin = User::factory()->superadmin()->create();

        return LegalFunctionAssignment::create([
            'user_id' => $superadmin->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'applicability_status' => LegalFunctionApplicability::PENDING_LEGAL_REVIEW,
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);
    }

    private function createProcessorAgreement(): ProcessorAgreement
    {
        $superadmin = User::factory()->superadmin()->create();

        return ProcessorAgreement::create([
            'counterparty_name' => 'Test Processor',
            'actor_user_id' => $superadmin->id,
        ]);
    }

    // ────────────────────────────────────────────────────────────
    //  DPO_APPLICABILITY scenarios
    // ────────────────────────────────────────────────────────────

    public function test_dpo_sequential_duplicate_returns_existing_active_review(): void
    {
        $assignment = $this->createDpoAssignment();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(LegalFunctionService::class);

        $c1 = $service->openApplicabilityReview($superadmin, $assignment, 'RULE-1');
        $c2 = $service->openApplicabilityReview($superadmin, $assignment, 'RULE-2');

        $this->assertSame($c1->id, $c2->id);
        $this->assertDatabaseCount('human_review_cases', 1);
        $this->assertDatabaseHas('human_review_cases', [
            'id' => $c1->id,
            'status' => HumanReviewStatus::PENDING->value,
        ]);
    }

    public function test_dpo_active_key_set_on_create(): void
    {
        $assignment = $this->createDpoAssignment();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(LegalFunctionService::class);

        $case = $service->openApplicabilityReview($superadmin, $assignment, 'RULE-1');

        $expectedKey = HumanReviewType::DPO_APPLICABILITY->value
            .':'.LegalFunctionAssignment::class
            .':'.$assignment->id;

        $this->assertDatabaseHas('human_review_cases', [
            'id' => $case->id,
            'active_review_key' => $expectedKey,
        ]);
    }

    public function test_dpo_seeded_pending_prevents_duplicate_via_unique_constraint(): void
    {
        $assignment = $this->createDpoAssignment();
        $superadmin = User::factory()->superadmin()->create();

        $activeKey = HumanReviewType::DPO_APPLICABILITY->value
            .':'.LegalFunctionAssignment::class
            .':'.$assignment->id;

        HumanReviewCase::create([
            'type' => HumanReviewType::DPO_APPLICABILITY,
            'active_review_key' => $activeKey,
            'rule_id' => 'SEED-RULE',
            'trigger' => 'Seeded for concurrency test',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'status' => HumanReviewStatus::PENDING,
        ]);

        $service = app(LegalFunctionService::class);
        $returned = $service->openApplicabilityReview($superadmin, $assignment, 'NEW-RULE');

        $this->assertSame($activeKey, $returned->active_review_key);
        $this->assertDatabaseCount('human_review_cases', 1);
    }

    public function test_dpo_active_key_cleared_on_apply(): void
    {
        $assignment = $this->createDpoAssignment();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(LegalFunctionService::class);

        $case = $service->openApplicabilityReview($superadmin, $assignment, 'RULE-1');

        $this->assertNotNull($case->active_review_key);

        // Decide the case
        $case->update([
            'decision' => 'REQUIRED',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => now(),
        ]);

        $service->applyApplicabilityReview($superadmin, $assignment, $case->id);

        $this->assertDatabaseHas('human_review_cases', [
            'id' => $case->id,
            'active_review_key' => null,
        ]);
    }

    public function test_dpo_resolved_old_review_allows_new_review(): void
    {
        $assignment = $this->createDpoAssignment();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(LegalFunctionService::class);

        // Open and resolve first review
        $c1 = $service->openApplicabilityReview($superadmin, $assignment, 'RULE-1');
        $c1->update([
            'decision' => 'REQUIRED',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => now(),
        ]);
        $service->applyApplicabilityReview($superadmin, $assignment, $c1->id);

        // Open new review for reassessment
        $c2 = $service->openApplicabilityReview($superadmin, $assignment, 'RULE-2');

        $this->assertNotSame($c1->id, $c2->id);
        $this->assertDatabaseCount('human_review_cases', 2);
        $this->assertDatabaseHas('human_review_cases', [
            'id' => $c1->id,
            'active_review_key' => null,
        ]);
        $this->assertDatabaseHas('human_review_cases', [
            'id' => $c2->id,
            'status' => HumanReviewStatus::PENDING->value,
        ]);
    }

    // ────────────────────────────────────────────────────────────
    //  PROCESSOR_ROLE scenarios
    // ────────────────────────────────────────────────────────────

    public function test_processor_sequential_duplicate_returns_existing_active_review(): void
    {
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(ProcessorAgreementService::class);

        $c1 = $service->openRoleReview($superadmin, $agreement, 'RULE-1');
        $c2 = $service->openRoleReview($superadmin, $agreement, 'RULE-2');

        $this->assertSame($c1->id, $c2->id);
        $this->assertDatabaseCount('human_review_cases', 1);
    }

    public function test_processor_active_key_set_on_create(): void
    {
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(ProcessorAgreementService::class);

        $case = $service->openRoleReview($superadmin, $agreement, 'RULE-1');

        $expectedKey = HumanReviewType::PROCESSOR_ROLE->value
            .':'.ProcessorAgreement::class
            .':'.$agreement->id;

        $this->assertDatabaseHas('human_review_cases', [
            'id' => $case->id,
            'active_review_key' => $expectedKey,
        ]);
    }

    public function test_processor_seeded_pending_prevents_duplicate_via_unique_constraint(): void
    {
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();

        $activeKey = HumanReviewType::PROCESSOR_ROLE->value
            .':'.ProcessorAgreement::class
            .':'.$agreement->id;

        HumanReviewCase::create([
            'type' => HumanReviewType::PROCESSOR_ROLE,
            'active_review_key' => $activeKey,
            'rule_id' => 'SEED-RULE',
            'trigger' => 'Seeded for concurrency test',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
            'status' => HumanReviewStatus::PENDING,
        ]);

        $service = app(ProcessorAgreementService::class);
        $returned = $service->openRoleReview($superadmin, $agreement, 'NEW-RULE');

        $this->assertSame($activeKey, $returned->active_review_key);
        $this->assertDatabaseCount('human_review_cases', 1);
    }

    public function test_processor_active_key_cleared_on_apply(): void
    {
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(ProcessorAgreementService::class);

        $case = $service->openRoleReview($superadmin, $agreement, 'RULE-1');

        $this->assertNotNull($case->active_review_key);

        $case->update([
            'decision' => 'PROCESSOR',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => now(),
        ]);

        $service->applyRoleReview($superadmin, $agreement, $case->id);

        $this->assertDatabaseHas('human_review_cases', [
            'id' => $case->id,
            'active_review_key' => null,
        ]);
    }

    public function test_processor_resolved_old_review_allows_new_review(): void
    {
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(ProcessorAgreementService::class);

        // Open and resolve first review
        $c1 = $service->openRoleReview($superadmin, $agreement, 'RULE-1');
        $c1->update([
            'decision' => 'PROCESSOR',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => now(),
        ]);
        $service->applyRoleReview($superadmin, $agreement, $c1->id);

        // Open new review for reassessment
        $c2 = $service->openRoleReview($superadmin, $agreement, 'RULE-2');

        $this->assertNotSame($c1->id, $c2->id);
        $this->assertDatabaseCount('human_review_cases', 2);
        $this->assertDatabaseHas('human_review_cases', [
            'id' => $c1->id,
            'active_review_key' => null,
        ]);
        $this->assertDatabaseHas('human_review_cases', [
            'id' => $c2->id,
            'status' => HumanReviewStatus::PENDING->value,
        ]);
    }

    // ────────────────────────────────────────────────────────────
    //  Cross-type isolation
    // ────────────────────────────────────────────────────────────

    public function test_dpo_and_processor_reviews_are_independent(): void
    {
        $assignment = $this->createDpoAssignment();
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();
        $dpoService = app(LegalFunctionService::class);
        $procService = app(ProcessorAgreementService::class);

        $c1 = $dpoService->openApplicabilityReview($superadmin, $assignment, 'DPO-RULE');
        $c2 = $procService->openRoleReview($superadmin, $agreement, 'PROC-RULE');

        $this->assertNotSame($c1->id, $c2->id);
        $this->assertDatabaseCount('human_review_cases', 2);
        $this->assertSame(HumanReviewType::DPO_APPLICABILITY->value, $c1->type->value);
        $this->assertSame(HumanReviewType::PROCESSOR_ROLE->value, $c2->type->value);
    }

    public function test_active_review_key_uniqueness_at_db_level(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        $assignment = $this->createDpoAssignment();

        $activeKey = HumanReviewType::DPO_APPLICABILITY->value
            .':'.LegalFunctionAssignment::class
            .':'.$assignment->id;

        DB::table('human_review_cases')->insert([
            'id' => Str::uuid()->toString(),
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => $activeKey,
            'rule_id' => 'R1',
            'trigger' => 'Test 1',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('human_review_cases')->insert([
            'id' => Str::uuid()->toString(),
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => $activeKey,
            'rule_id' => 'R2',
            'trigger' => 'Test 2',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_null_active_review_key_allows_multiple_resolved_cases(): void
    {
        $assignment = $this->createDpoAssignment();

        DB::table('human_review_cases')->insert([
            'id' => Str::uuid()->toString(),
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => null,
            'rule_id' => 'R1',
            'trigger' => 'Resolved 1',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'status' => HumanReviewStatus::APPROVED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('human_review_cases')->insert([
            'id' => Str::uuid()->toString(),
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => null,
            'rule_id' => 'R2',
            'trigger' => 'Resolved 2',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'status' => HumanReviewStatus::APPROVED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('human_review_cases', 2);
    }

    // ────────────────────────────────────────────────────────────
    //  Two-connection race simulation
    // ────────────────────────────────────────────────────────────

    public function test_concurrent_two_connection_race_dpo(): void
    {
        $assignment = $this->createDpoAssignment();

        $activeKey = HumanReviewType::DPO_APPLICABILITY->value
            .':'.LegalFunctionAssignment::class
            .':'.$assignment->id;

        $caseId1 = Str::uuid()->toString();
        $caseId2 = Str::uuid()->toString();

        // Simulate TX1: insert with active key (uncommitted)
        DB::table('human_review_cases')->insert([
            'id' => $caseId1,
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => $activeKey,
            'rule_id' => 'CONCURRENT-R1',
            'trigger' => 'Race test TX1',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulate TX2: try to insert with same key (should fail due to unique constraint)
        try {
            DB::table('human_review_cases')->insert([
                'id' => $caseId2,
                'type' => HumanReviewType::DPO_APPLICABILITY->value,
                'active_review_key' => $activeKey,
                'rule_id' => 'CONCURRENT-R2',
                'trigger' => 'Race test TX2',
                'subject_type' => LegalFunctionAssignment::class,
                'subject_id' => $assignment->id,
                'status' => HumanReviewStatus::PENDING->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('Expected UniqueConstraintViolationException was not thrown');
        } catch (UniqueConstraintViolationException $e) {
            // MySQL includes index name; SQLite includes column name
            $this->assertTrue(
                str_contains($e->getMessage(), 'hrc_active_review_unique')
                    || str_contains($e->getMessage(), 'active_review_key'),
                'Expected unique constraint violation on active_review_key: '.$e->getMessage(),
            );
        }

        // Exactly one active case exists
        $this->assertDatabaseCount('human_review_cases', 1);
        $this->assertDatabaseHas('human_review_cases', [
            'id' => $caseId1,
            'active_review_key' => $activeKey,
        ]);
    }

    public function test_concurrent_two_connection_race_processor(): void
    {
        $agreement = $this->createProcessorAgreement();

        $activeKey = HumanReviewType::PROCESSOR_ROLE->value
            .':'.ProcessorAgreement::class
            .':'.$agreement->id;

        $caseId1 = Str::uuid()->toString();
        $caseId2 = Str::uuid()->toString();

        DB::table('human_review_cases')->insert([
            'id' => $caseId1,
            'type' => HumanReviewType::PROCESSOR_ROLE->value,
            'active_review_key' => $activeKey,
            'rule_id' => 'CONCURRENT-R1',
            'trigger' => 'Race test TX1',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('human_review_cases')->insert([
                'id' => $caseId2,
                'type' => HumanReviewType::PROCESSOR_ROLE->value,
                'active_review_key' => $activeKey,
                'rule_id' => 'CONCURRENT-R2',
                'trigger' => 'Race test TX2',
                'subject_type' => ProcessorAgreement::class,
                'subject_id' => $agreement->id,
                'status' => HumanReviewStatus::PENDING->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('Expected UniqueConstraintViolationException was not thrown');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'hrc_active_review_unique')
                    || str_contains($e->getMessage(), 'active_review_key'),
                'Expected unique constraint violation on active_review_key: '.$e->getMessage(),
            );
        }

        $this->assertDatabaseCount('human_review_cases', 1);
        $this->assertDatabaseHas('human_review_cases', [
            'id' => $caseId1,
            'active_review_key' => $activeKey,
        ]);
    }

    // ────────────────────────────────────────────────────────────
    //  Parent link integrity under race
    // ────────────────────────────────────────────────────────────

    public function test_parent_link_integrity_dpo_after_duplicate_return(): void
    {
        $assignment = $this->createDpoAssignment();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(LegalFunctionService::class);

        $case = $service->openApplicabilityReview($superadmin, $assignment, 'RULE-1');
        $service->openApplicabilityReview($superadmin, $assignment, 'RULE-2');

        $assignment->refresh();

        $this->assertSame($case->id, $assignment->human_review_case_id);
        $this->assertDatabaseCount('human_review_cases', 1);
    }

    public function test_parent_link_integrity_processor_after_duplicate_return(): void
    {
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(ProcessorAgreementService::class);

        $case = $service->openRoleReview($superadmin, $agreement, 'RULE-1');
        $service->openRoleReview($superadmin, $agreement, 'RULE-2');

        $agreement->refresh();

        $this->assertSame($case->id, $agreement->human_review_case_id);
        $this->assertDatabaseCount('human_review_cases', 1);
    }
}
