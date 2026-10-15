<?php

declare(strict_types=1);

namespace Tests\Feature\Web\Admin;

use App\Enums\B2gEvidenceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RfqStatus;
use App\Models\B2gEvidenceSubmission;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_metrics_and_attention_counters(): void
    {
        $admin = User::factory()->superadmin()->create();

        User::factory()->pending()->count(2)->create();

        $applicant = User::factory()->buyerB2g()->create();
        foreach ([B2gEvidenceStatus::SUBMITTED, B2gEvidenceStatus::UNDER_REVIEW] as $status) {
            B2gEvidenceSubmission::create([
                'user_id' => $applicant->id,
                'version' => $status === B2gEvidenceStatus::SUBMITTED ? 1 : 2,
                'submitted_by' => $applicant->id,
                'path' => 'evidence/'.$status->value.'.pdf',
                'original_filename' => 'bukti.pdf',
                'mime' => 'application/pdf',
                'size' => 1024,
                'evidence_type' => 'INSTITUTION_LETTER',
                'status' => $status,
            ]);
        }

        Rfq::factory()->count(2)->create(['status' => RfqStatus::APPROVED]);
        Invoice::factory()->create(['status' => InvoiceStatus::REVIEW_REQUIRED]);
        Invoice::factory()->count(2)->create(['status' => InvoiceStatus::OVERDUE]);
        Payment::factory()->count(3)->create(['status' => PaymentStatus::PENDING_VERIFICATION]);

        $this->actingAs($admin)
            ->get('/admin/dasbor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard/Index')
                ->where('attention.pending_accounts', 2)
                ->where('attention.evidence_awaiting_review', 2)
                ->where('attention.rfqs_awaiting_conversion', 2)
                ->where('attention.payments_pending_verification', 3)
                ->where('attention.invoices_review_required', 1)
                ->where('attention.invoices_overdue', 2)
                ->has('metrics.rfqs')
                ->has('metrics.orders')
                ->has('metrics.outstanding_receivables')
                ->has('metrics.verified_payments')
                ->has('metrics.tkdn_compliance')
                ->has('metrics.generated_at'));
    }

    public function test_dashboard_attention_counters_default_to_zero(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->get('/admin/dasbor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('attention.pending_accounts', 0)
                ->where('attention.evidence_awaiting_review', 0)
                ->where('attention.rfqs_awaiting_conversion', 0)
                ->where('attention.payments_pending_verification', 0)
                ->where('attention.invoices_review_required', 0)
                ->where('attention.invoices_overdue', 0));
    }
}
