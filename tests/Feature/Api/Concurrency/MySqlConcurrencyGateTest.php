<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Concurrency;

use App\Enums\HumanReviewStatus;
use App\Enums\HumanReviewType;
use App\Enums\LegalFunctionApplicability;
use App\Enums\LegalFunctionStatus;
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

class MySqlConcurrencyGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL-specific concurrency gate test.');
        }
    }

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

    public function test_mysql_dpo_concurrent_two_connection_race(): void
    {
        $assignment = $this->createDpoAssignment();

        $activeKey = HumanReviewType::DPO_APPLICABILITY->value
            .':'.LegalFunctionAssignment::class
            .':'.$assignment->id;

        $caseId1 = Str::uuid()->toString();
        $caseId2 = Str::uuid()->toString();

        $conn = DB::connection('mysql');

        $conn->beginTransaction();
        $conn->table('human_review_cases')->insert([
            'id' => $caseId1,
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => $activeKey,
            'rule_id' => 'MYSQL-RACE-R1',
            'trigger' => 'MySQL race TX1',
            'subject_type' => LegalFunctionAssignment::class,
            'subject_id' => $assignment->id,
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        try {
            $conn->table('human_review_cases')->insert([
                'id' => $caseId2,
                'type' => HumanReviewType::DPO_APPLICABILITY->value,
                'active_review_key' => $activeKey,
                'rule_id' => 'MYSQL-RACE-R2',
                'trigger' => 'MySQL race TX2',
                'subject_type' => LegalFunctionAssignment::class,
                'subject_id' => $assignment->id,
                'status' => HumanReviewStatus::PENDING->value,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

            $this->fail('Expected UniqueConstraintViolationException was not thrown');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('active_review_key', $e->getMessage());
        }

        $conn->rollBack();

        $this->assertDatabaseCount('human_review_cases', 0);
    }

    public function test_mysql_processor_concurrent_two_connection_race(): void
    {
        $agreement = $this->createProcessorAgreement();

        $activeKey = HumanReviewType::PROCESSOR_ROLE->value
            .':'.ProcessorAgreement::class
            .':'.$agreement->id;

        $caseId1 = Str::uuid()->toString();
        $caseId2 = Str::uuid()->toString();

        $conn = DB::connection('mysql');

        $conn->beginTransaction();
        $conn->table('human_review_cases')->insert([
            'id' => $caseId1,
            'type' => HumanReviewType::PROCESSOR_ROLE->value,
            'active_review_key' => $activeKey,
            'rule_id' => 'MYSQL-RACE-R1',
            'trigger' => 'MySQL race TX1',
            'subject_type' => ProcessorAgreement::class,
            'subject_id' => $agreement->id,
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        try {
            $conn->table('human_review_cases')->insert([
                'id' => $caseId2,
                'type' => HumanReviewType::PROCESSOR_ROLE->value,
                'active_review_key' => $activeKey,
                'rule_id' => 'MYSQL-RACE-R2',
                'trigger' => 'MySQL race TX2',
                'subject_type' => ProcessorAgreement::class,
                'subject_id' => $agreement->id,
                'status' => HumanReviewStatus::PENDING->value,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

            $this->fail('Expected UniqueConstraintViolationException was not thrown');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('active_review_key', $e->getMessage());
        }

        $conn->rollBack();

        $this->assertDatabaseCount('human_review_cases', 0);
    }

    public function test_mysql_hrc_active_review_unique_index_enforced(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        $conn = DB::connection('mysql');

        $key = 'UNIQUE-TEST:SomeSubject:123';

        $conn->table('human_review_cases')->insert([
            'id' => Str::uuid()->toString(),
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => $key,
            'rule_id' => 'R1',
            'trigger' => 'Test1',
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $conn->table('human_review_cases')->insert([
            'id' => Str::uuid()->toString(),
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => $key,
            'rule_id' => 'R2',
            'trigger' => 'Test2',
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_mysql_resolved_review_allows_new_key(): void
    {
        $conn = DB::connection('mysql');
        $key = 'RESOLVED-TEST:Subject:456';

        $conn->table('human_review_cases')->insert([
            'id' => Str::uuid()->toString(),
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => $key,
            'rule_id' => 'R1',
            'trigger' => 'Test1',
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseCount('human_review_cases', 1);

        $this->assertTrue($this->resolveAndOpenNew($conn, $key));

        $this->assertDatabaseCount('human_review_cases', 2);
    }

    private function resolveAndOpenNew($conn, string $oldKey): bool
    {
        $conn->beginTransaction();

        $conn->table('human_review_cases')
            ->where('active_review_key', $oldKey)
            ->update(['active_review_key' => null, 'status' => HumanReviewStatus::APPROVED->value]);

        $newKey = 'NEW-KEY:Subject:789';
        $conn->table('human_review_cases')->insert([
            'id' => Str::uuid()->toString(),
            'type' => HumanReviewType::DPO_APPLICABILITY->value,
            'active_review_key' => $newKey,
            'rule_id' => 'R2',
            'trigger' => 'Test2',
            'status' => HumanReviewStatus::PENDING->value,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $conn->commit();

        return true;
    }

    public function test_mysql_dpo_service_open_returns_existing_on_race(): void
    {
        $assignment = $this->createDpoAssignment();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(LegalFunctionService::class);

        $c1 = $service->openApplicabilityReview($superadmin, $assignment, 'MYSQL-RULE-1');
        $c2 = $service->openApplicabilityReview($superadmin, $assignment, 'MYSQL-RULE-2');

        $this->assertSame($c1->id, $c2->id);
        $this->assertDatabaseCount('human_review_cases', 1);
        $this->assertDatabaseHas('human_review_cases', [
            'id' => $c1->id,
            'status' => HumanReviewStatus::PENDING->value,
        ]);
    }

    public function test_mysql_processor_service_open_returns_existing_on_race(): void
    {
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(ProcessorAgreementService::class);

        $c1 = $service->openRoleReview($superadmin, $agreement, 'MYSQL-RULE-1');
        $c2 = $service->openRoleReview($superadmin, $agreement, 'MYSQL-RULE-2');

        $this->assertSame($c1->id, $c2->id);
        $this->assertDatabaseCount('human_review_cases', 1);
    }

    public function test_mysql_dpo_resolved_then_new_review_allowed(): void
    {
        $assignment = $this->createDpoAssignment();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(LegalFunctionService::class);

        $c1 = $service->openApplicabilityReview($superadmin, $assignment, 'R1');
        $c1->update([
            'decision' => 'REQUIRED',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => now(),
        ]);
        $service->applyApplicabilityReview($superadmin, $assignment, $c1->id);

        $c2 = $service->openApplicabilityReview($superadmin, $assignment, 'R2');

        $this->assertNotSame($c1->id, $c2->id);
        $this->assertDatabaseCount('human_review_cases', 2);
        $this->assertDatabaseHas('human_review_cases', ['id' => $c1->id, 'active_review_key' => null]);
        $this->assertDatabaseHas('human_review_cases', ['id' => $c2->id, 'status' => HumanReviewStatus::PENDING->value]);
    }

    public function test_mysql_processor_resolved_then_new_review_allowed(): void
    {
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(ProcessorAgreementService::class);

        $c1 = $service->openRoleReview($superadmin, $agreement, 'R1');
        $c1->update([
            'decision' => 'PROCESSOR',
            'status' => HumanReviewStatus::APPROVED,
            'decided_at' => now(),
        ]);
        $service->applyRoleReview($superadmin, $agreement, $c1->id);

        $c2 = $service->openRoleReview($superadmin, $agreement, 'R2');

        $this->assertNotSame($c1->id, $c2->id);
        $this->assertDatabaseCount('human_review_cases', 2);
        $this->assertDatabaseHas('human_review_cases', ['id' => $c1->id, 'active_review_key' => null]);
        $this->assertDatabaseHas('human_review_cases', ['id' => $c2->id, 'status' => HumanReviewStatus::PENDING->value]);
    }

    public function test_mysql_dpo_parent_link_integrity(): void
    {
        $assignment = $this->createDpoAssignment();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(LegalFunctionService::class);

        $case = $service->openApplicabilityReview($superadmin, $assignment, 'R1');
        $service->openApplicabilityReview($superadmin, $assignment, 'R2');

        $assignment->refresh();
        $this->assertSame($case->id, $assignment->human_review_case_id);
        $this->assertDatabaseCount('human_review_cases', 1);
    }

    public function test_mysql_processor_parent_link_integrity(): void
    {
        $agreement = $this->createProcessorAgreement();
        $superadmin = User::factory()->superadmin()->create();
        $service = app(ProcessorAgreementService::class);

        $case = $service->openRoleReview($superadmin, $agreement, 'R1');
        $service->openRoleReview($superadmin, $agreement, 'R2');

        $agreement->refresh();
        $this->assertSame($case->id, $agreement->human_review_case_id);
        $this->assertDatabaseCount('human_review_cases', 1);
    }
}
