<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\SubmitPaymentRequest;
use App\Http\Requests\Payment\VerifyPaymentRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    /**
     * Submit a payment proof for an invoice.
     */
    public function store(SubmitPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('create', [Payment::class, $invoice]);

        if ($invoice->status === InvoiceStatus::PAID) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice sudah lunas.',
                'data' => null,
                'errors' => ['invoice_id' => ['Invoice ini sudah berstatus PAID.']],
            ], 422);
        }

        $file = $request->file('proof_file');
        $path = Storage::disk('public')->putFile('payments/proofs', $file);

        $payment = DB::transaction(function () use ($request, $invoice, $path) {
            return Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
                'amount' => $request->input('amount'),
                'payment_method' => $request->input('payment_method'),
                'payment_date' => $request->input('payment_date'),
                'proof_file_url' => Storage::disk('public')->url($path),
                'notes' => $request->input('notes'),
            ]);
        });

        $payment->load('user', 'invoice');

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil dikirim',
            'data' => new PaymentResource($payment),
            'errors' => null,
        ], 201);
    }

    /**
     * Display a listing of the payments.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Payment::query()->with('user', 'invoice');

        // Scope: Superadmin sees all, buyers see only payments for their own invoices
        if ($user->role !== UserRole::SUPERADMIN) {
            $query->whereHas('invoice', fn ($invoice) => $invoice->whereHas('order', fn ($order) => $order->where('user_id', $user->id)));
        }

        // Filter by verification status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->input('per_page', 15);
        $payments = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Payment listing retrieved',
            'data' => PaymentResource::collection($payments),
            'errors' => null,
        ], 200);
    }

    /**
     * Verify or reject a payment and reconcile the invoice.
     */
    public function verify(VerifyPaymentRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('verify', $payment);

        if ($payment->status !== PaymentStatus::PENDING_VERIFICATION) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran sudah diproses.',
                'data' => null,
                'errors' => ['status' => ['Hanya pembayaran PENDING_VERIFICATION yang dapat diverifikasi.']],
            ], 422);
        }

        $newStatus = PaymentStatus::from($request->input('status'));

        DB::transaction(function () use ($payment, $request, $newStatus) {
            // Lock the payment and its invoice to serialize concurrent verifications
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

            $lockedPayment->update([
                'status' => $newStatus,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'rejection_reason' => $newStatus === PaymentStatus::REJECTED
                    ? $request->input('rejection_reason')
                    : null,
            ]);

            $this->reconcileInvoice($invoice);
        });

        $payment->refresh();
        $payment->load('user', 'verifiedBy', 'invoice');

        return response()->json([
            'success' => true,
            'message' => $newStatus === PaymentStatus::VERIFIED
                ? 'Pembayaran berhasil diverifikasi'
                : 'Pembayaran ditolak',
            'data' => new PaymentResource($payment),
            'errors' => null,
        ], 200);
    }

    /**
     * Reconcile verified payments against the invoice grand total.
     */
    private function reconcileInvoice(Invoice $invoice): void
    {
        $verifiedAmount = $invoice->verifiedPaidAmount();
        $grandTotal = (string) $invoice->grand_total;

        if (bccomp($verifiedAmount, $grandTotal, 2) >= 0) {
            $invoice->update([
                'status' => InvoiceStatus::PAID,
                'paid_at' => now(),
            ]);

            return;
        }

        if (bccomp($verifiedAmount, '0.00', 2) > 0) {
            $invoice->update([
                'status' => InvoiceStatus::PARTIALLY_PAID,
            ]);

            return;
        }

        $invoice->update([
            'status' => InvoiceStatus::UNPAID,
        ]);
    }
}
