<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Order;

use App\Enums\AuditAction;
use App\Enums\OrderStatus;
use App\Enums\RfqStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderFromRfqRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\Order\OrderResource;
use App\Models\BastDocument;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Rfq;
use App\Models\User;
use App\Notifications\OrderShippedNotification;
use App\Services\AuditLogger;
use App\Services\PdfService;
use App\Services\UniqueIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display a listing of the orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::query()->with(['user', 'items.product', 'bastDocument', 'invoices']);

        // Scope: Superadmin sees all, buyers see only their own
        if ($user->role !== UserRole::SUPERADMIN) {
            $query->where('user_id', $user->id);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = $this->perPage($request);
        $orders = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Order listing retrieved',
            'data' => OrderResource::collection($orders),
            'errors' => null,
        ], 200);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load('user', 'items.product', 'bastDocument', 'invoices');

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved',
            'data' => new OrderResource($order),
            'errors' => null,
        ], 200);
    }

    /**
     * Convert an approved RFQ into an order.
     */
    public function store(CreateOrderFromRfqRequest $request): JsonResponse
    {
        $rfq = Rfq::with('items.product')->findOrFail($request->input('rfq_id'));

        $this->authorize('create', [Order::class, $rfq]);

        if ($rfq->status !== RfqStatus::APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya RFQ yang disetujui yang dapat dikonversi menjadi pesanan.',
                'data' => null,
                'errors' => ['rfq_id' => ['RFQ harus berstatus APPROVED untuk dikonversi.']],
            ], 422);
        }

        try {
            $order = DB::transaction(function () use ($rfq) {
                // Acquire an exclusive row lock on the RFQ to prevent concurrent
                // conversion (TOCTOU). The orders.rfq_id UNIQUE index is the
                // final safety net; this lock keeps concurrent transactions
                // from both reaching the INSERT.
                $rfq->lockForUpdate();

                if (Order::where('rfq_id', $rfq->id)->exists()) {
                    throw new QueryException(
                        'Duplicate entry: rfq already converted',
                        'Duplicate entry: rfq already converted',
                        [],
                        new \Exception('23000'),
                    );
                }

                $order = Order::create([
                    'order_number' => UniqueIdentifier::generate('ORD', Order::class, 'order_number'),
                    'user_id' => $rfq->user_id,
                    'rfq_id' => $rfq->id,
                    'status' => OrderStatus::PENDING_PAYMENT,
                    'top_days' => 30,
                ]);

                foreach ($rfq->items as $item) {
                    $orderItem = new OrderItem([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        // Freeze the commercial identity at order time (INT-7) so
                        // later catalog changes never alter this order or invoice.
                        'product_sku_snapshot' => $item->product->sku,
                        'product_title_snapshot' => $item->product->title,
                        'ppn_rate_snapshot' => $item->product->tax_rate_percentage,
                        'pph_rate_snapshot' => $item->product->pph_rate_percentage,
                    ]);

                    // unit_price is not mass-assignable; set directly. Fallback to
                    // the product's base price if the RFQ item has no negotiated price.
                    $orderItem->unit_price = $item->negotiated_price ?? $item->product->base_price;
                    $orderItem->save();
                }

                $order->recalculateTotal();
                $rfq->update(['status' => RfqStatus::CONVERTED_TO_ORDER]);

                return $order;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return response()->json([
                    'success' => false,
                    'message' => 'RFQ sudah pernah dikonversi menjadi pesanan.',
                    'data' => null,
                    'errors' => ['rfq_id' => ['RFQ ini sudah memiliki pesanan.']],
                ], 422);
            }
            throw $e;
        }

        $order->load('user', 'items.product', 'bastDocument', 'invoices');

        $this->auditLogger->log($request->user(), AuditAction::ORDER_CREATED, $order);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat',
            'data' => new OrderResource($order),
            'errors' => null,
        ], 201);
    }

    /**
     * Determine whether a QueryException represents a unique constraint violation.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        // MySQL: SQLSTATE 23000; SQLite: "UNIQUE constraint failed"
        $sqlState = $e->errorInfo[0] ?? null;
        $message = $e->getMessage();

        return $sqlState === '23000'
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');
    }

    /**
     * Update the order status.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('updateStatus', $order);

        $newStatus = OrderStatus::from($request->input('status'));
        $currentStatus = $order->status;

        if (! $this->isValidTransition($currentStatus, $newStatus, $request->user(), $order)) {
            return response()->json([
                'success' => false,
                'message' => 'Transisi status tidak valid.',
                'data' => null,
                'errors' => ['status' => ['Tidak dapat mengubah status dari '.$currentStatus->value.' ke '.$newStatus->value.'.']],
            ], 422);
        }

        $previousState = $this->auditLogger->snapshot($order);

        $order = DB::transaction(function () use ($order, $newStatus) {
            $order->update(['status' => $newStatus]);

            // Auto-generate a BAST record (and its draft) when the order is
            // shipped, ready for signing once the goods arrive
            if ($newStatus === OrderStatus::SHIPPED && $order->bastDocument()->doesntExist()) {
                $order->bastDocument()->create([
                    'bast_number' => UniqueIdentifier::generate('BAST', BastDocument::class, 'bast_number'),
                ]);
            }

            return $order;
        });

        // Notify the buyer that the goods have been shipped and they should
        // prepare to sign the BAST once the delivery arrives
        if ($newStatus === OrderStatus::SHIPPED) {
            $order->user->notify(new OrderShippedNotification($order));
        }

        // Generate the BAST draft PDF. Failures are logged by the service and
        // leave the URL null so the status update still succeeds.
        if ($newStatus === OrderStatus::SHIPPED && $order->bastDocument) {
            $bast = $order->bastDocument;

            $path = $this->pdfService->generate(
                'pdf.bast',
                ['bast' => $bast->load('order.user', 'order.items.product')],
                'bast',
                'BAST-'.$bast->bast_number.'.pdf'
            );

            if ($path !== '') {
                $bast->update(['bast_document_url' => $path]);
            }
        }

        $order->load('user', 'items.product', 'bastDocument', 'invoices');

        $this->auditLogger->log($request->user(), AuditAction::ORDER_STATUS_UPDATED, $order, $previousState);

        if ($newStatus === OrderStatus::SHIPPED && $order->bastDocument) {
            $this->auditLogger->log($request->user(), AuditAction::BAST_CREATED, $order->bastDocument);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil diperbarui',
            'data' => new OrderResource($order),
            'errors' => null,
        ], 200);
    }

    /**
     * Validate order status transition rules.
     */
    private function isValidTransition(OrderStatus $from, OrderStatus $to, User $user, Order $order): bool
    {
        $allowedTransitions = [
            OrderStatus::PENDING_PAYMENT->value => [
                OrderStatus::PROCESSING->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::PROCESSING->value => [
                OrderStatus::SHIPPED->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::SHIPPED->value => [
                OrderStatus::DELIVERED->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::DELIVERED->value => [
                OrderStatus::COMPLETED->value,
                OrderStatus::CANCELLED->value,
            ],
            OrderStatus::COMPLETED->value => [],
            OrderStatus::CANCELLED->value => [],
        ];

        $allowed = $allowedTransitions[$from->value] ?? [];
        $isAllowed = in_array($to->value, $allowed, true);

        if (! $isAllowed) {
            return false;
        }

        // Forward moves are Superadmin-only; cancellation is allowed for the owner
        if ($to === OrderStatus::CANCELLED) {
            return $user->role === UserRole::SUPERADMIN || $user->is($order->user);
        }

        return $user->role === UserRole::SUPERADMIN;
    }
}
