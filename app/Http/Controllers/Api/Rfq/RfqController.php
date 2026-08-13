<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Rfq;

use App\Enums\AuditAction;
use App\Enums\RfqStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rfq\RespondRfqRequest;
use App\Http\Requests\Rfq\StoreRfqRequest;
use App\Http\Requests\Rfq\UpdateRfqStatusRequest;
use App\Http\Resources\Rfq\RfqResource;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use App\Notifications\RfqRespondedNotification;
use App\Notifications\RfqSubmittedNotification;
use App\Services\AuditLogger;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class RfqController extends Controller
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display a listing of the RFQs.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Rfq::query()->with(['user', 'items.product']);

        // Scope: Superadmin sees all, buyers see only their own
        if ($user->role !== UserRole::SUPERADMIN) {
            $query->where('user_id', $user->id);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = $request->input('per_page', 15);
        $rfqs = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'RFQ listing retrieved',
            'data' => RfqResource::collection($rfqs),
            'errors' => null,
        ], 200);
    }

    /**
     * Store a newly created RFQ.
     */
    public function store(StoreRfqRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $items = $validated['items'] ?? [];
        $rfqNumber = 'RFQ-'.strtoupper(Str::random(10));

        $rfq = DB::transaction(function () use ($validated, $items, $rfqNumber, $request) {
            $rfq = Rfq::create([
                'rfq_number' => $rfqNumber,
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                RfqItem::create([
                    'rfq_id' => $rfq->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'target_price' => $item['target_price'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $rfq->load('items.product', 'user');
        });

        // Notify all Superadmins that a new RFQ is awaiting a quotation
        Notification::send(
            User::where('role', UserRole::SUPERADMIN)->get(),
            new RfqSubmittedNotification($rfq)
        );

        $this->auditLogger->log($request->user(), AuditAction::RFQ_CREATED, $rfq);

        return response()->json([
            'success' => true,
            'message' => 'RFQ berhasil dibuat',
            'data' => new RfqResource($rfq),
            'errors' => null,
        ], 201);
    }

    /**
     * Display the specified RFQ.
     */
    public function show(Request $request, Rfq $rfq): JsonResponse
    {
        $this->authorize('view', $rfq);

        $rfq->load('user', 'items.product');

        return response()->json([
            'success' => true,
            'message' => 'RFQ retrieved',
            'data' => new RfqResource($rfq),
            'errors' => null,
        ], 200);
    }

    /**
     * Superadmin responds to RFQ with quoted prices.
     */
    public function respond(RespondRfqRequest $request, Rfq $rfq): JsonResponse
    {
        $this->authorize('respond', $rfq);

        $validated = $request->validated();
        $items = $validated['items'] ?? [];
        $previousState = $this->auditLogger->snapshot($rfq);

        // Validate ownership before transaction
        foreach ($items as $item) {
            $rfqItem = RfqItem::find($item['rfq_item_id']);

            if (! $rfqItem || $rfqItem->rfq_id !== $rfq->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'rfq_item_id does not belong to this RFQ.',
                    'data' => null,
                    'errors' => ['items.*.rfq_item_id' => ['rfq_item_id does not belong to this RFQ.']],
                ], 422);
            }
        }

        $rfq = DB::transaction(function () use ($rfq, $validated, $items) {
            // Update each RFQ item with offered price (stored in negotiated_price)
            foreach ($items as $item) {
                $rfqItem = RfqItem::find($item['rfq_item_id']);
                $rfqItem->update([
                    'negotiated_price' => $item['offered_price'],
                ]);
            }

            // Update RFQ with quotation details
            $rfq->update([
                'status' => RfqStatus::QUOTED,
                'valid_until' => $validated['valid_until'],
                'admin_notes' => $validated['admin_notes'] ?? null,
            ]);

            return $rfq->load('items.product', 'user');
        });

        // Notify the buyer that the quotation is ready
        $rfq->user->notify(new RfqRespondedNotification($rfq));

        $this->auditLogger->log($request->user(), AuditAction::RFQ_QUOTED, $rfq, $previousState);

        // Generate the Surat Penawaran Harga PDF. Failures are logged by the
        // service and leave the URL null so the business action still succeeds.
        $path = $this->pdfService->generate(
            'pdf.rfq-quotation',
            ['rfq' => $rfq],
            'rfq',
            'Surat-Penawaran-Harga-'.$rfq->rfq_number.'.pdf'
        );

        if ($path !== '') {
            $rfq->update(['quotation_pdf_url' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penawaran harga berhasil disimpan',
            'data' => new RfqResource($rfq),
            'errors' => null,
        ], 200);
    }

    /**
     * Update RFQ status (accept, reject, cancel).
     */
    public function updateStatus(UpdateRfqStatusRequest $request, Rfq $rfq): JsonResponse
    {
        $this->authorize('updateStatus', $rfq);

        $newStatus = RfqStatus::from($request->input('status'));
        $currentStatus = $rfq->status;
        $previousState = $this->auditLogger->snapshot($rfq);

        // Validate status transitions
        if (! $this->isValidTransition($currentStatus, $newStatus, $request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Transisi status tidak valid.',
                'data' => null,
                'errors' => ['status' => ['Tidak dapat mengubah status dari '.$currentStatus->value.' ke '.$newStatus->value.'.']],
            ], 422);
        }

        $rfq->update(['status' => $newStatus]);
        $rfq->load('user', 'items.product');

        $this->auditLogger->log($request->user(), AuditAction::RFQ_STATUS_UPDATED, $rfq, $previousState);

        return response()->json([
            'success' => true,
            'message' => 'Status RFQ berhasil diperbarui',
            'data' => new RfqResource($rfq),
            'errors' => null,
        ], 200);
    }

    /**
     * Validate status transition rules.
     */
    private function isValidTransition(RfqStatus $from, RfqStatus $to, User $user): bool
    {
        // Superadmin can do anything
        if ($user->role === UserRole::SUPERADMIN) {
            return true;
        }

        // Owner transitions
        $ownerTransitions = [
            RfqStatus::SUBMITTED->value => [RfqStatus::CANCELLED->value],
            RfqStatus::QUOTED->value => [RfqStatus::APPROVED->value, RfqStatus::REJECTED->value, RfqStatus::CANCELLED->value],
        ];

        $fromValue = $from->value;

        if (isset($ownerTransitions[$fromValue])) {
            return in_array($to->value, $ownerTransitions[$fromValue], true);
        }

        return false;
    }
}
