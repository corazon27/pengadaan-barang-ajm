<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Payment;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\PaymentOverpaymentException;
use App\Exceptions\PaymentReviewRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\SubmitPaymentRequest;
use App\Http\Requests\Payment\VerifyPaymentRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\PaymentVerifiedNotification;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

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

        if ($invoice->status === InvoiceStatus::REVIEW_REQUIRED) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice menunggu perhitungan pajak dan belum dapat dibayar.',
                'data' => null,
                'errors' => ['invoice_id' => ['Invoice berstatus REVIEW_REQUIRED dan belum dapat menerima pembayaran.']],
            ], 422);
        }

        $file = $request->file('proof_file');
        $path = Storage::disk('documents')->putFile('payments/proofs', $file);

        $payment = DB::transaction(function () use ($request, $invoice, $path) {
            return Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()->id,
                'amount' => $request->input('amount'),
                'payment_method' => $request->input('payment_method'),
                'payment_date' => $request->input('payment_date'),
                'proof_file_url' => $path,
                'notes' => $request->input('notes'),
            ]);
        });

        $payment->load('user', 'invoice');

        $this->auditLogger->log($request->user(), AuditAction::PAYMENT_SUBMITTED, $payment);

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil dikirim',
            'data' => new PaymentResource($payment),
            'errors' => null,
        ], 201);
    }

    /**
     * Stream the stored payment proof to an authorized requester.
     */
    public function downloadProof(Payment $payment): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $payment);

        $path = $payment->proof_file_url;

        if (! $path || ! Storage::disk('documents')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Bukti pembayaran tidak ditemukan.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        return Storage::disk('documents')->response($path);
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

        $perPage = $this->perPage($request);
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
        $previousPaymentState = $this->auditLogger->snapshot($payment);
        $previousInvoiceState = $this->auditLogger->snapshot($payment->invoice);

        try {
            DB::transaction(function () use ($payment, $request, $newStatus) {
                // Lock the payment and its invoice to serialize concurrent verifications
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

                if ($newStatus === PaymentStatus::VERIFIED) {
                    $this->guardAgainstOverpayment($invoice, $lockedPayment->amount);
                }

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
        } catch (PaymentOverpaymentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verifikasi pembayaran ditolak: jumlah melebihi total tagihan.',
                'data' => null,
                'errors' => ['amount' => [$e->getMessage()]],
            ], 422);
        } catch (PaymentReviewRequiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice berstatus REVIEW_REQUIRED dan belum dapat direkonsiliasi.',
                'data' => null,
                'errors' => ['invoice_id' => [$e->getMessage()]],
            ], 422);
        }

        $payment->refresh();
        $payment->load('user', 'verifiedBy', 'invoice');

        $this->auditLogger->log(
            $request->user(),
            $newStatus === PaymentStatus::VERIFIED ? AuditAction::PAYMENT_VERIFIED : AuditAction::PAYMENT_REJECTED,
            $payment,
            $previousPaymentState
        );

        $reconciledInvoice = Invoice::find($payment->invoice_id);
        if ($reconciledInvoice && ($reconciledInvoice->status->value ?? null) !== ($previousInvoiceState['status'] ?? null)) {
            $this->auditLogger->log($request->user(), AuditAction::INVOICE_STATUS_UPDATED, $reconciledInvoice, $previousInvoiceState);
        }

        // Notify the buyer that their payment has been verified. The invoice is
        // re-fetched to reflect the reconciled status (PAID / PARTIALLY_PAID).
        if ($newStatus === PaymentStatus::VERIFIED) {
            $invoice = $reconciledInvoice ?? Invoice::find($payment->invoice_id);

            if ($invoice && $invoice->order) {
                $invoice->order->user->notify(new PaymentVerifiedNotification($payment, $invoice));
            }
        }

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
     * Reject verification when the payment would push the verified total past
     * the invoice grand total (INT-4). Uses BC Math; exact and partial-settlement
     * sums are allowed.
     *
     * @param  float|int|string  $amount
     */
    private function guardAgainstOverpayment(Invoice $invoice, $amount): void
    {
        $projected = bcadd($invoice->verifiedPaidAmount(), (string) $amount, 2);
        $grandTotal = (string) $invoice->grand_total;

        if (bccomp($projected, $grandTotal, 2) > 0) {
            throw new PaymentOverpaymentException(
                "Total pembayaran terverifikasi Rp{$projected} melebihi tagihan Rp{$grandTotal}."
            );
        }
    }

    /**
     * Reconcile verified payments against the invoice grand total.
     *
     * An invoice on REVIEW_REQUIRED hold must never be reconciled: its billed
     * amounts are provisional and payment semantics do not apply until tax is
     * resolved. The guard throws so the enclosing transaction rolls back and
     * the payment stays untouched.
     */
    private function reconcileInvoice(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::REVIEW_REQUIRED) {
            throw new PaymentReviewRequiredException(
                'Invoice berstatus REVIEW_REQUIRED dan belum dapat direkonsiliasi.'
            );
        }

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
