<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Policies\AdminPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SUPERADMIN read-only payment queue. Console gate via {@see AdminPolicy};
 * detail re-authorizes the record through PaymentPolicy@view.
 *
 * The payment proof file path (proof_file_url) is NEVER serialized. Only a
 * boolean "has_proof" flag is exposed so the operator knows evidence exists;
 * there is no download link because no adequately protected/audited download
 * route exists for this surface (a known gap — see the module report).
 */
class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view', AdminPolicy::class);

        $status = PaymentStatus::tryFrom((string) $request->input('status'));

        $paginator = Payment::query()
            ->with('invoice.order.user:id,company_name')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $payments = $paginator->map(fn (Payment $payment): array => [
            'id' => $payment->getKey(),
            'invoice_number' => $payment->invoice?->invoice_number,
            'buyer_company' => $payment->invoice?->order?->user?->company_name,
            'amount' => (string) $payment->amount,
            'payment_method_label' => $payment->paymentMethodLabel(),
            'payment_date' => $payment->payment_date?->toDateString(),
            'status' => $payment->status->value,
            'status_label' => $payment->status->statusLabel(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'pagination' => $this->pagination($paginator, '/admin/pembayaran'),
            'filters' => ['status' => $status?->value],
            'statuses' => array_map(
                fn (PaymentStatus $case): array => ['value' => $case->value, 'label' => $case->statusLabel()],
                PaymentStatus::cases(),
            ),
        ]);
    }

    public function show(Payment $payment): Response
    {
        $this->authorize('view', AdminPolicy::class);
        $this->authorize('view', $payment);

        $payment->load(['invoice.order.user:id,company_name,full_name', 'verifiedBy:id,full_name']);

        return Inertia::render('Admin/Payments/Show', [
            'payment' => [
                'id' => $payment->getKey(),
                'invoice_number' => $payment->invoice?->invoice_number,
                'order_number' => $payment->invoice?->order?->order_number,
                'buyer_company' => $payment->invoice?->order?->user?->company_name,
                'buyer_name' => $payment->invoice?->order?->user?->full_name,
                'amount' => (string) $payment->amount,
                'payment_method_label' => $payment->paymentMethodLabel(),
                'payment_date' => $payment->payment_date?->toDateString(),
                'notes' => $payment->notes,
                'status' => $payment->status->value,
                'status_label' => $payment->status->statusLabel(),
                'has_proof' => $payment->proof_file_url !== null,
                'verified_by_name' => $payment->verifiedBy?->full_name,
                'verified_at' => $payment->verified_at?->toIso8601String(),
                'rejection_reason' => $payment->rejection_reason,
                'created_at' => $payment->created_at?->toIso8601String(),
            ],
        ]);
    }
}
