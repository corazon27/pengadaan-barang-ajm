<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bast;

use App\Enums\AuditAction;
use App\Enums\BastStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTerm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bast\SignBastRequest;
use App\Http\Resources\Bast\BastResource;
use App\Models\BastDocument;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BastController extends Controller
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly AuditLogger $auditLogger,
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

        $bast = DB::transaction(function () use ($request, $order, $bast) {
            $bast->update([
                'status' => BastStatus::SIGNED,
                'signed_by' => $request->user()->id,
                'signed_at' => now(),
                'signed_date' => now()->toDateString(),
                'notes' => $request->input('notes'),
            ]);

            $order->update(['status' => OrderStatus::COMPLETED]);

            if ($order->invoices()->doesntExist()) {
                $this->generateInvoice($order, $bast);
            }

            return $bast->fresh(['order', 'signedBy']);
        });

        $this->auditLogger->log($request->user(), AuditAction::BAST_SIGNED, $bast, $previousState);
        $this->auditLogger->log($request->user(), AuditAction::ORDER_STATUS_UPDATED, $order, $previousOrderState);

        if ($hadNoInvoice && $order->invoices()->exists()) {
            $this->auditLogger->log($request->user(), AuditAction::INVOICE_CREATED, $order->invoices()->first());
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
     */
    private function generateInvoice(Order $order, BastDocument $bast): Invoice
    {
        $invoiceNumber = 'INV-'.strtoupper(Str::random(10));

        $subtotal = (float) $order->total_amount;

        // PPN and optional PPh withholding are computed per item from the
        // product rates, using BC Math for 2-decimal precision.
        [$ppnAmount, $pphAmount] = $order->items()->with('product')->get()->reduce(
            function (array $carry, $item) {
                $ppnRate = (float) ($item->product->tax_rate_percentage ?? 0);
                $pphRate = (float) ($item->product->pph_rate_percentage ?? 0);

                $carry[0] = bcadd($carry[0], bcmul((string) $item->subtotal, (string) ($ppnRate / 100), 6), 2);
                $carry[1] = bcadd($carry[1], bcmul((string) $item->subtotal, (string) ($pphRate / 100), 6), 2);

                return $carry;
            },
            ['0.00', '0.00']
        );

        // PPh is a withholding (deducted by the buyer) and is NOT added to the
        // billed amount: grand_total = subtotal + PPN.
        $grandTotal = bcadd((string) $subtotal, $ppnAmount, 2);

        $paymentTerm = $this->resolvePaymentTerm((int) $order->top_days);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'bast_id' => $bast->id,
            'invoice_number' => $invoiceNumber,
            'invoice_pdf_url' => '',
            'amount_due' => $grandTotal,
            'subtotal' => $subtotal,
            'ppn_amount' => $ppnAmount,
            'pph_amount' => $pphAmount,
            'payment_term' => $paymentTerm,
            'grand_total' => $grandTotal,
            'issued_date' => now()->toDateString(),
            'due_date' => now()->addDays($paymentTerm->days())->toDateString(),
        ]);

        // Generate the invoice PDF. Failures are logged by the service and
        // leave the URL empty so the invoice is still issued.
        $path = $this->pdfService->generate(
            'pdf.invoice',
            ['invoice' => $invoice->load('order.user', 'order.items.product')],
            'invoice',
            'Invoice-'.$invoiceNumber.'.pdf'
        );

        if ($path !== '') {
            $invoice->update(['invoice_pdf_url' => $path]);
        }

        return $invoice;
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
