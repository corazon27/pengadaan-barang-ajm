<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the authenticated user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Notification listing retrieved',
            'data' => [
                'items' => NotificationResource::collection($notifications),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ],
            ],
            'errors' => null,
        ], 200);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => new NotificationResource($notification),
            'errors' => null,
        ], 200);
    }

    /**
     * Mark all unread notifications as read for the current user.
     */
    public function readAll(Request $request): JsonResponse
    {
        $count = $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => ['marked_as_read' => $count],
            'errors' => null,
        ], 200);
    }
}
