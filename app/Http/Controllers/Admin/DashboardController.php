<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\B2gEvidenceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RfqStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\B2gEvidenceSubmission;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rfq;
use App\Models\User;
use App\Policies\AdminPolicy;
use App\Services\AnalyticsMetricsService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SUPERADMIN read-only dashboard. Aggregated KPIs come from the shared
 * {@see AnalyticsMetricsService} (identical to the analytics API) and the
 * "needs attention" counters are bounded aggregate queries over the same enum
 * states the operational queues filter on, so each counter links to the queue
 * that represents it. Nothing here mutates state or writes audit rows.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsMetricsService $metrics,
    ) {}

    public function index(): Response
    {
        $this->authorize('view', AdminPolicy::class);

        return Inertia::render('Admin/Dashboard/Index', [
            'metrics' => $this->metrics->snapshot(),
            'attention' => [
                'pending_accounts' => User::query()
                    ->where('status', UserStatus::PENDING)
                    ->count(),
                'evidence_awaiting_review' => B2gEvidenceSubmission::query()
                    ->whereIn('status', [
                        B2gEvidenceStatus::SUBMITTED,
                        B2gEvidenceStatus::UNDER_REVIEW,
                    ])
                    ->count(),
                'rfqs_awaiting_conversion' => Rfq::query()
                    ->where('status', RfqStatus::APPROVED)
                    ->count(),
                'payments_pending_verification' => Payment::query()
                    ->where('status', PaymentStatus::PENDING_VERIFICATION)
                    ->count(),
                'invoices_review_required' => Invoice::query()
                    ->where('status', InvoiceStatus::REVIEW_REQUIRED)
                    ->count(),
                'invoices_overdue' => Invoice::query()
                    ->where('status', InvoiceStatus::OVERDUE)
                    ->count(),
            ],
        ]);
    }
}
