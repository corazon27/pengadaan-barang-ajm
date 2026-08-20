<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\BreachNotificationStatus;
use App\Enums\BreachNotificationType;
use App\Events\BreachNotificationSent;
use App\Models\BreachNotification;
use App\Models\IncidentRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BreachNotificationService
{
    private const VALID_TRANSITIONS = [
        BreachNotificationStatus::PENDING->value => [
            BreachNotificationStatus::PREPARING,
            BreachNotificationStatus::CANCELLED,
        ],
        BreachNotificationStatus::PREPARING->value => [
            BreachNotificationStatus::READY,
            BreachNotificationStatus::CANCELLED,
        ],
        BreachNotificationStatus::READY->value => [
            BreachNotificationStatus::SENT,
            BreachNotificationStatus::CANCELLED,
        ],
        BreachNotificationStatus::SENT->value => [
            BreachNotificationStatus::CONFIRMED,
            BreachNotificationStatus::FAILED,
        ],
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Prepare a new breach notification. Initial status = PENDING.
     */
    public function prepareNotification(
        IncidentRegister $incident,
        User $actor,
        BreachNotificationType $type,
        string $recipient,
        ?array $contentSnapshot = null,
    ): BreachNotification {
        $existing = BreachNotification::where('incident_id', $incident->id)
            ->where('notification_type', $type)
            ->exists();

        if ($existing) {
            throw new \InvalidArgumentException(
                "Notification type {$type->value} already exists for this incident"
            );
        }

        return DB::transaction(function () use ($incident, $actor, $type, $recipient, $contentSnapshot) {
            $notification = BreachNotification::create([
                'incident_id' => $incident->id,
                'notification_type' => $type,
                'recipient' => $recipient,
                'status' => BreachNotificationStatus::PENDING,
                'content_snapshot' => $contentSnapshot,
                'actor_user_id' => $actor->id,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::BREACH_NOTIFICATION_PREPARED,
                $notification,
                [],
                [
                    'notification_type' => $type->value,
                    'recipient' => $recipient,
                    'status' => BreachNotificationStatus::PENDING->value,
                ],
            );

            return $notification;
        });
    }

    /**
     * Transition notification to PREPARING.
     */
    public function markPreparing(
        BreachNotification $notification,
        User $actor,
    ): BreachNotification {
        return $this->transition($notification, BreachNotificationStatus::PREPARING, $actor);
    }

    /**
     * Transition notification to READY (awaiting human approval).
     */
    public function markReady(
        BreachNotification $notification,
        User $actor,
    ): BreachNotification {
        return $this->transition($notification, BreachNotificationStatus::READY, $actor);
    }

    /**
     * Send a breach notification. Requires READY status first.
     * For AUTHORITY type, this triggers the statutory timer completion signal.
     */
    public function sendNotification(
        BreachNotification $notification,
        User $actor,
    ): BreachNotification {
        $notification = $this->transition($notification, BreachNotificationStatus::SENT, $actor);

        $notification->update([
            'sent_at' => now(),
            'actor_user_id' => $actor->id,
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::BREACH_NOTIFICATION_SENT,
            $notification,
            ['status' => BreachNotificationStatus::READY->value],
            ['status' => BreachNotificationStatus::SENT->value],
        );

        $incident = $notification->incident;

        if ($notification->notification_type === BreachNotificationType::AUTHORITY) {
            event(new BreachNotificationSent(
                notification: $notification,
                incident: $incident,
            ));
        }

        return $notification->refresh();
    }

    /**
     * Acknowledge delivery confirmation.
     */
    public function acknowledgeDelivery(
        BreachNotification $notification,
        User $actor,
    ): BreachNotification {
        $notification = $this->transition($notification, BreachNotificationStatus::CONFIRMED, $actor);

        $notification->update([
            'confirmed_at' => now(),
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::BREACH_NOTIFICATION_ACKNOWLEDGED,
            $notification,
            ['status' => BreachNotificationStatus::SENT->value],
            ['status' => BreachNotificationStatus::CONFIRMED->value],
        );

        return $notification->refresh();
    }

    /**
     * Mark notification as failed.
     */
    public function markFailed(
        BreachNotification $notification,
        User $actor,
        string $reason,
    ): BreachNotification {
        $notification = $this->transition($notification, BreachNotificationStatus::FAILED, $actor);

        $notification->update([
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);

        $this->auditLogger->log(
            $actor,
            AuditAction::BREACH_NOTIFICATION_FAILED,
            $notification,
            ['status' => BreachNotificationStatus::SENT->value],
            [
                'status' => BreachNotificationStatus::FAILED->value,
                'failure_reason' => $reason,
            ],
        );

        return $notification->refresh();
    }

    /**
     * Cancel a notification (only from PENDING, PREPARING, or READY).
     */
    public function cancelNotification(
        BreachNotification $notification,
        User $actor,
    ): BreachNotification {
        $notification = $this->transition($notification, BreachNotificationStatus::CANCELLED, $actor);

        $this->auditLogger->log(
            $actor,
            AuditAction::BREACH_NOTIFICATION_CANCELLED,
            $notification,
            [],
            ['status' => BreachNotificationStatus::CANCELLED->value],
        );

        return $notification->refresh();
    }

    /**
     * Validate and execute a state transition without audit logging.
     * Public methods handle their own audit logging for meaningful transitions.
     */
    private function transition(
        BreachNotification $notification,
        BreachNotificationStatus $target,
        User $actor,
    ): BreachNotification {
        $current = $notification->status;

        $allowed = self::VALID_TRANSITIONS[$current->value] ?? [];

        if (! in_array($target, $allowed)) {
            throw new \InvalidArgumentException(
                "Invalid notification transition from {$current->value} to {$target->value}"
            );
        }

        $notification->update([
            'status' => $target,
        ]);

        return $notification->refresh();
    }
}
