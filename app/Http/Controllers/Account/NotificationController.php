<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer-facing, read-only notification list.
 *
 * The query is structurally bound to the authenticated user's own
 * `notifications()` relation, so it can never return another user's rows; the
 * `viewAny` gate is still checked explicitly. Only an allowlist of safe fields
 * is serialized — the raw notification `data` blob is never passed through.
 * This surface is read-only (no mark-as-read mutation).
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Notification::class);

        $paginator = $request->user()
            ->notifications()
            ->latest()
            ->paginate(15);

        $notifications = $paginator->map(fn (DatabaseNotification $notification): array => [
            'id' => $notification->getKey(),
            'type' => class_basename($notification->type),
            'title' => $notification->data['title'] ?? null,
            'message' => $notification->data['message'] ?? null,
            'action_url' => $notification->data['action_url'] ?? null,
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render('Account/Notifications/Index', [
            'notifications' => $notifications,
            'pagination' => $this->pagination($paginator, '/akun/notifikasi'),
        ]);
    }
}
