<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer-facing, read-only invoice (tagihan) views.
 *
 * Ownership is derived through the invoice → order chain, both for the listing
 * scope and the `InvoicePolicy@view` gate. Payment evidence file paths are
 * deliberately never serialized on this surface.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $paginator = Invoice::query()
            ->visibleTo($request->user())
            ->with('order')
            ->latest('issued_date')
            ->latest('id')
            ->paginate(15);

        $invoices = $paginator->map(fn (Invoice $invoice): array => [
            'id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'order_number' => $invoice->order?->order_number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->statusLabel(),
            'payment_term_label' => $invoice->payment_term?->statusLabel(),
            'grand_total' => (string) $invoice->grand_total,
            'amount_due' => (string) $invoice->amount_due,
            'issued_date' => $invoice->issued_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
        ])->values();

        return Inertia::render('Account/Invoices/Index', [
            'invoices' => $invoices,
            'pagination' => $this->pagination($paginator, '/akun/tagihan'),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['order', 'payments']);

        return Inertia::render('Account/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'order_number' => $invoice->order?->order_number,
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
