<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Incident;

use App\Enums\BreachNotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\StoreBreachNotificationRequest;
use App\Http\Resources\Incident\BreachNotificationResource;
use App\Models\BreachNotification;
use App\Models\IncidentRegister;
use App\Services\BreachNotificationService;
use Illuminate\Http\JsonResponse;

class BreachNotificationController extends Controller
{
    public function __construct(
        private readonly BreachNotificationService $notificationService,
    ) {}

    public function index(IncidentRegister $incident): JsonResponse
    {
        $this->authorize('viewAny', BreachNotification::class);

        $notifications = $incident->breachNotifications()->paginate($this->perPage(request()));

        return response()->json([
            'success' => true,
            'message' => 'Daftar notifikasi pelanggaran berhasil dimuat',
            'data' => BreachNotificationResource::collection($notifications),
            'errors' => null,
        ], 200);
    }

    public function store(StoreBreachNotificationRequest $request, IncidentRegister $incident): JsonResponse
    {
        $this->authorize('create', BreachNotification::class);

        try {
            $notification = $this->notificationService->prepareNotification(
                incident: $incident,
                actor: $request->user(),
                type: BreachNotificationType::from($request->validated('notification_type')),
                recipient: $request->validated('recipient'),
                contentSnapshot: $request->validated('content_snapshot'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['notification_type' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi pelanggaran berhasil disiapkan',
            'data' => new BreachNotificationResource($notification),
            'errors' => null,
        ], 201);
    }

    public function show(IncidentRegister $incident, BreachNotification $notification): JsonResponse
    {
        $this->authorize('view', $notification);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi pelanggaran berhasil dimuat',
            'data' => new BreachNotificationResource($notification),
            'errors' => null,
        ], 200);
    }

    public function send(IncidentRegister $incident, BreachNotification $notification): JsonResponse
    {
        $this->authorize('send', $notification);

        try {
            $notification = $this->notificationService->sendNotification(
                notification: $notification,
                actor: request()->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['status' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi pelanggaran berhasil dikirim',
            'data' => new BreachNotificationResource($notification),
            'errors' => null,
        ], 200);
    }

    public function acknowledge(IncidentRegister $incident, BreachNotification $notification): JsonResponse
    {
        $this->authorize('acknowledge', $notification);

        $notification = $this->notificationService->acknowledgeDelivery(
            notification: $notification,
            actor: request()->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengiriman notifikasi berhasil dikonfirmasi',
            'data' => new BreachNotificationResource($notification),
            'errors' => null,
        ], 200);
    }

    public function cancel(IncidentRegister $incident, BreachNotification $notification): JsonResponse
    {
        $this->authorize('cancel', $notification);

        try {
            $notification = $this->notificationService->cancelNotification(
                notification: $notification,
                actor: request()->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['status' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi pelanggaran berhasil dibatalkan',
            'data' => new BreachNotificationResource($notification),
            'errors' => null,
        ], 200);
    }
}
