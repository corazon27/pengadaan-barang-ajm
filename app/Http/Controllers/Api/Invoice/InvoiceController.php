<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Invoice;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\UpdateInvoicePaymentStatusRequest;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Invoice;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Display a listing of the invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Invoice::query()->with('order');

        // Scope: Superadmin sees all, buyers see only their own orders' invoices
        if ($user->role !== UserRole::SUPERADMIN) {
            $query->whereHas('order', fn ($order) => $order->where('user_id', $user->id));
        }

        // Filter by payment status
        if ($paymentStatus = $request->input('payment_status')) {
            $query->where('status', $paymentStatus);
        }

        $perPage = $request->input('per_page', 15);
        $invoices = $query->latest('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Invoice listing retrieved',
            'data' => InvoiceResource::collection($invoices),
            'errors' => null,
        ], 200);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load('order', 'payments');

        return response()->json([
            'success' => true,
            'message' => 'Invoice retrieved',
            'data' => new InvoiceResource($invoice),
            'errors' => null,
        ], 200);
    }

    /**
     * Update the invoice payment status.
     */
    public function updatePaymentStatus(UpdateInvoicePaymentStatusRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('updatePaymentStatus', $invoice);

        $paymentStatus = InvoiceStatus::from($request->input('payment_status'));
        $previousState = $this->auditLogger->snapshot($invoice);

        $invoice->update([
            'status' => $paymentStatus,
            'paid_at' => $paymentStatus === InvoiceStatus::PAID ? now() : null,
        ]);

        $this->auditLogger->log($request->user(), AuditAction::INVOICE_STATUS_UPDATED, $invoice, $previousState);

        return response()->json([
            'success' => true,
            'message' => 'Status pembayaran invoice berhasil diperbarui',
            'data' => new InvoiceResource($invoice->fresh('order')),
            'errors' => null,
        ], 200);
    }
}
