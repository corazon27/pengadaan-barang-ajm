<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bast;

use App\Enums\AuditAction;
use App\Enums\BastStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTerm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bast\SignBastRequest;
use App\Http\Resources\Bast\BastResource;
use App\Models\BastDocument;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\InvoiceTaxService;
use App\Services\PdfService;
use App\Services\UniqueIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BastController extends Controller
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly AuditLogger $auditLogger,
        private readonly InvoiceTaxService $invoiceTaxService,
    ) {}

    /**
     * Display the BAST document for an order.
     */
    public function show(Order $order): JsonResponse
    {
        $bast = $order->bastDocument()->with('signedBy')->first();

        if (! $bast) {
            return response()->json([
                'success' => false,
                'message' => 'BAST belum tersedia untuk pesanan ini.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        $this->authorize('view', $bast);

        return response()->json([
            'success' => true,
            'message' => 'BAST retrieved',
            'data' => new BastResource($bast),
            'errors' => null,
        ], 200);
    }

    /**
     * Sign the BAST document and finalize the order.
     */
    public function sign(SignBastRequest $request, Order $order): JsonResponse
    {
        $bast = $order->bastDocument()->first();

        if (! $bast) {
            return response()->json([
                'success' => false,
                'message' => 'BAST belum tersedia untuk pesanan ini.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        $this->authorize('sign', $bast);

        if ($bast->status !== BastStatus::PENDING_SIGNATURE) {
            return response()->json([
                'success' => false,
                'message' => 'BAST ini sudah ditandatangani.',
                'data' => null,
                'errors' => ['status' => ['BAST sudah berstatus '.$bast->status->value.'.']],
            ], 422);
        }

        if ($order->status !== OrderStatus::DELIVERED) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan belum diterima, BAST belum dapat ditandatangani.',
                'data' => null,
                'errors' => ['order_status' => ['Pesanan harus berstatus DELIVERED untuk menandatangani BAST.']],
            ], 422);
        }

        $previousState = $this->auditLogger->snapshot($bast);
        $previousOrderState = $this->auditLogger->snapshot($order);
        $hadNoInvoice = $order->invoices()->doesntExist();

        $lockedOrder = null;

        $bast = DB::transaction(function () use ($request, $order, $bast, &$lockedOrder) {
            // Lock the order row so concurrent BAST sign requests serialize;
            // the second request re-reads the latest committed state and skips
            // invoice generation (see invoices_order_unique constraint).
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $bast->update([
                'status' => BastStatus::SIGNED,
                'signed_by' => $request->user()->id,
                'signed_at' => now(),
                'signed_date' => now()->toDateString(),
                'notes' => $request->input('notes'),
            ]);

            $lockedOrder->update(['status' => OrderStatus::COMPLETED]);

            if ($lockedOrder->invoices()->doesntExist()) {
                $this->generateInvoice($lockedOrder, $bast);
            }

            return $bast->fresh(['order', 'signedBy']);
        });

        $this->auditLogger->log($request->user(), AuditAction::BAST_SIGNED, $bast, $previousState);
        $this->auditLogger->log($request->user(), AuditAction::ORDER_STATUS_UPDATED, $lockedOrder, $previousOrderState);

        if ($hadNoInvoice && $lockedOrder->invoices()->exists()) {
            $this->auditLogger->log($request->user(), AuditAction::INVOICE_CREATED, $lockedOrder->invoices()->first());
        }

        return response()->json([
            'success' => true,
            'message' => 'BAST berhasil ditandatangani',
            'data' => new BastResource($bast),
            'errors' => null,
        ], 200);
    }

    /**
     * Generate an invoice tied to the order and its signed BAST.
     *
     * The invoice row is created first (provisional, zero tax) so the tax run
     * is idempotent and audit-visible even on hold. PPN is then computed by
     * InvoiceTaxService from the frozen Stage-1 commercial context. On hold the
     * invoice stays REVIEW_REQUIRED with zero tax and no snapshots; the legacy
     * PPh withholding remains informational only and is never added to the
     * billed amount (grand_total = subtotal + PPN).
     */
    private function generateInvoice(Order $order, BastDocument $bast): Invoice
    {
        $invoiceNumber = UniqueIdentifier::generate('INV', Invoice::class, 'invoice_number');

        // The order total was already finalized with BC Math (INT-5).
        $subtotal = (string) $order->total_amount;

        // PPh withholding is a withholding (deducted by the buyer), kept as
        // informational value from the frozen order-time snapshot (INT-7).
        $pphAmount = $order->items()->get()->reduce(
            function (string $carry, $item) {
                $pphRate = (string) ($item->pph_rate_snapshot ?? 0);

                return bcadd($carry, bcmul((string) $item->subtotal, bcdiv($pphRate, '100', 6), 6), 2);
            },
            '0.00'
        );

        $paymentTerm = $this->resolvePaymentTerm((int) $order->top_days);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'bast_id' => $bast->id,
            'invoice_number' => $invoiceNumber,
            'invoice_pdf_url' => '',
            'amount_due' => '0.00',
            'subtotal' => $subtotal,
            'ppn_amount' => '0.00',
            'pph_amount' => $pphAmount,
            'payment_term' => $paymentTerm,
            'grand_total' => '0.00',
            'issued_date' => now()->toDateString(),
            'due_date' => now()->addDays($paymentTerm->days())->toDateString(),
        ]);

        // The BAST signing moment is the operative tax event: rules are
        // resolved and computed against today.
        $outcome = $this->invoiceTaxService->applyToInvoice($invoice, $order, now()->copy());

        if ($outcome->isAuthoritative()) {
            $grandTotal = $outcome->grandTotal();

            $invoice->update([
                'amount_due' => $grandTotal,
                'ppn_amount' => $outcome->ppnAmount,
                'grand_total' => $grandTotal,
                'status' => InvoiceStatus::UNPAID,
                'tax_calculation_version' => $outcome->calculationVersion,
            ]);
        } else {
            $provisionalState = $this->auditLogger->snapshot($invoice);

            $invoice->update([
                'amount_due' => '0.00',
                'ppn_amount' => '0.00',
                'grand_total' => '0.00',
                'status' => InvoiceStatus::REVIEW_REQUIRED,
            ]);

            $this->auditLogger->log(
                null,
                AuditAction::TAX_RESOLUTION_REVIEW_REQUIRED,
                $invoice,
                $provisionalState,
                array_merge($this->auditLogger->snapshot($invoice), [
                    'hold_reason_code' => $outcome->holdReasonCode,
                    'resolved_lines' => $outcome->resolvedLineCount,
                    'total_lines' => $outcome->lineCount,
                    'tax_calculation_version' => $outcome->calculationVersion,
                ]),
            );
        }

        // Generate the invoice PDF. Failures are logged by the service and
        // leave the URL empty so the invoice is still issued.
        $path = $this->pdfService->generate(
            'pdf.invoice',
            ['invoice' => $invoice->load('order.user', 'order.items.product', 'ruleSnapshots')],
            'invoice',
            'Invoice-'.$invoiceNumber.'.pdf'
        );

        if ($path !== '') {
            $invoice->update(['invoice_pdf_url' => $path]);
        }

        return $invoice->refresh();
    }

    /**
     * Map an order's term-of-payment (in days) to a PaymentTerm enum.
     */
    private function resolvePaymentTerm(int $topDays): PaymentTerm
    {
        return match ($topDays) {
            0 => PaymentTerm::IMMEDIATE,
            14 => PaymentTerm::TOP_14,
            60 => PaymentTerm::TOP_60,
            default => PaymentTerm::TOP_30,
        };
    }
}
