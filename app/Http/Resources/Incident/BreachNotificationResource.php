<?php

declare(strict_types=1);

namespace App\Http\Resources\Incident;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreachNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incident_id' => $this->incident_id,
            'notification_type' => $this->notification_type?->value,
            'recipient' => $this->recipient,
            'status' => $this->status?->value,
            'content_snapshot' => $this->content_snapshot,
            'prepared_at' => $this->prepared_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'evidence_reference' => $this->evidence_reference,
            'actor_user_id' => $this->actor_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
