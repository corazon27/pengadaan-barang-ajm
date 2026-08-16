<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\HumanReviewStatus;
use App\Enums\HumanReviewType;
use App\Events\RegulatoryReferenceCreated;
use App\Listeners\LogRegulatoryReferenceCreated;
use App\Models\HumanReviewCase;
use App\Models\LegalFunctionAssignment;
use App\Models\Product;
use App\Models\RegulatoryReference;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ComplianceKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_regulatory_reference_is_persisted_with_defaults(): void
    {
        $reference = RegulatoryReference::create([
            'reference_code' => 'UU-27-2022',
            'title' => 'Undang-Undang Perlindungan Data Pribadi',
            'effective_from' => '2022-10-17',
            'source_version' => 'official',
        ]);

        $this->assertDatabaseHas('regulatory_references', [
            'id' => $reference->id,
            'reference_code' => 'UU-27-2022',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_regulatory_reference_creation_dispatches_event_through_module8_audit_logs(): void
    {
        Event::fake([RegulatoryReferenceCreated::class]);

        $reference = RegulatoryReference::create([
            'reference_code' => 'PERMENDAG-31-2023',
            'title' => 'PMSE',
            'effective_from' => '2023-01-01',
            'source_version' => 'official',
        ]);

        Event::assertDispatched(RegulatoryReferenceCreated::class, function ($event) use ($reference) {
            return $event->reference->is($reference);
        });

        // The listener routes through the existing Module 8 audit_logs table.
        // Invoke it directly so the single canonical audit path is asserted
        // independently of queue/event-fake behavior.
        (new LogRegulatoryReferenceCreated(new AuditLogger))
            ->handle(new RegulatoryReferenceCreated($reference));

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'RegulatoryReference',
            'entity_id' => $reference->id,
        ]);
    }

    public function test_human_review_case_is_single_canonical_abstraction(): void
    {
        $case = HumanReviewCase::create([
            'type' => HumanReviewType::SUPPLIER_ELIGIBILITY->value,
            'rule_id' => 'B2B-01',
            'trigger' => 'NPWP mismatch on approval',
            'capability_required' => 'review_supplier_eligibility',
            'legal_function_required' => null,
            'evidence_snapshot' => ['npwp' => '9999999999', 'score' => 0.4],
        ]);

        $this->assertSame(HumanReviewStatus::PENDING, $case->status);
        $this->assertSame('B2B-01', $case->rule_id);
        $this->assertSame(['npwp' => '9999999999', 'score' => 0.4], $case->evidence_snapshot);
        $this->assertDatabaseHas('human_review_cases', [
            'id' => $case->id,
            'type' => HumanReviewType::SUPPLIER_ELIGIBILITY->value,
            'status' => HumanReviewStatus::PENDING->value,
        ]);
    }

    public function test_human_review_case_decision_is_recorded(): void
    {
        $case = HumanReviewCase::create([
            'type' => HumanReviewType::TAX->value,
            'rule_id' => 'TAX-PPN-01',
            'trigger' => 'collector classification',
        ]);

        $case->update([
            'status' => HumanReviewStatus::APPROVED,
            'decision' => 'APPROVE',
            'reason' => 'NIB verified',
            'reviewed_by' => User::factory()->create()->id,
            'decided_at' => now(),
        ]);

        $this->assertSame(HumanReviewStatus::APPROVED, $case->fresh()->status);
        $this->assertSame('APPROVE', $case->fresh()->decision);
        $this->assertNotNull($case->fresh()->decided_at);
    }

    public function test_legal_function_assignment_preserves_role_function_separation(): void
    {
        // A legal-function assignment requires an explicit appointment basis.
        // It must NOT be derivable from an RBAC role; here we assert the
        // schema holds real appointment semantics only.
        $assignment = LegalFunctionAssignment::create([
            'user_id' => User::factory()->create()->id,
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'statutory_basis' => 'Ps 46 UU 27/2022',
            'appointment_basis' => 'board resolution',
            'effective_from' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $this->assertSame('DPO', $assignment->function_code);
        $this->assertSame('Ps 46 UU 27/2022', $assignment->statutory_basis);
        $this->assertSame('board resolution', $assignment->appointment_basis);
        $this->assertDatabaseHas('legal_function_assignments', [
            'id' => $assignment->id,
            'function_code' => 'DPO',
            'applicability_status' => 'PENDING_LEGAL_REVIEW',
        ]);
    }

    public function test_legal_function_assignment_defaults_to_inactive(): void
    {
        $assignment = LegalFunctionAssignment::create([
            'function_code' => 'PIC',
            'function_category' => 'PROCUREMENT',
        ]);

        $this->assertSame('INACTIVE', $assignment->status);
        $this->assertSame('PENDING_LEGAL_REVIEW', $assignment->applicability_status);
    }

    public function test_module8_audit_logger_unchanged_compatibility(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $previous = (new AuditLogger)->snapshot($product);

        (new AuditLogger)->log(
            $user,
            AuditAction::PRODUCT_CREATED,
            $product,
            $previous,
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::PRODUCT_CREATED->value,
            'entity_type' => 'Product',
            'entity_id' => $product->id,
        ]);
    }
}
