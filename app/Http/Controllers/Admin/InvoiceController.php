<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Policies\AdminPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SUPERADMIN read-only invoice (tagihan) queue. Console gate via
 * {@see AdminPolicy}; detail re-authorizes the record through InvoicePolicy@view.
 * Payment evidence file paths (invoice_pdf_url, faktur_pajak_url) and the
 * payment proof path are deliberately never serialized here. No mutations — in
 * particular the tax recalculation flow is NOT exposed.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view', AdminPolicy::class);

        $status = InvoiceStatus::tryFrom((string) $request->input('status'));

        $paginator = Invoice::query()
            ->with('order.user:id,company_name')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest('issued_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $invoices = $paginator->map(fn (Invoice $invoice): array => [
            'id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'order_number' => $invoice->order?->order_number,
            'buyer_company' => $invoice->order?->user?->company_name,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->statusLabel(),
            'payment_term_label' => $invoice->payment_term?->statusLabel(),
            'grand_total' => (string) $invoice->grand_total,
            'amount_due' => (string) $invoice->amount_due,
            'issued_date' => $invoice->issued_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
        ])->values();

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $invoices,
            'pagination' => $this->pagination($paginator, '/admin/tagihan'),
            'filters' => ['status' => $status?->value],
            'statuses' => array_map(
                fn (InvoiceStatus $case): array => ['value' => $case->value, 'label' => $case->statusLabel()],
                InvoiceStatus::cases(),
            ),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', AdminPolicy::class);
        $this->authorize('view', $invoice);

        $invoice->load(['order.user:id,company_name,full_name', 'payments']);

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'order_number' => $invoice->order?->order_number,
                'buyer_company' => $invoice->order?->user?->company_name,
                'buyer_name' => $invoice->order?->user?->full_name,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->statusLabel(),
                'payment_term_label' => $invoice->payment_term?->statusLabel(),
                'faktur_pajak_number' => $invoice->faktur_pajak_number,
                'subtotal' => (string) $invoice->subtotal,
                'ppn_amount' => (string) $invoice->ppn_amount,
                'pph_amount' => (string) $invoice->pph_amount,
                'grand_total' => (string) $invoice->grand_total,
                'amount_due' => (string) $invoice->amount_due,
                'paid_amount' => $invoice->payments
                    ->where('status', PaymentStatus::VERIFIED)
                    ->reduce(
                        fn (string $carry, Payment $payment): string => bcadd($carry, (string) $payment->amount, 2),
                        '0.00',
                    ),
                'issued_date' => $invoice->issued_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                    'id' => $payment->getKey(),
                    'amount' => (string) $payment->amount,
                    'payment_method_label' => $payment->paymentMethodLabel(),
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'status' => $payment->status->value,
                    'status_label' => $payment->status->statusLabel(),
                    'rejection_reason' => $payment->rejection_reason,
                ])->values(),
            ],
        ]);
    }
}
