<?php

declare(strict_types=1);

namespace App\Http\Resources\Rfq;

use App\Enums\RfqStatus;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items');

        return [
            'id' => $this->id,
            'rfq_number' => $this->rfq_number,
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'items' => $items ? RfqItemResource::collection($items) : [],
            'status' => $this->status?->value,
            'status_label' => $this->status ? $this->statusLabel() : null,
            'valid_until' => $this->valid_until?->toISOString(),
            'admin_notes' => $this->admin_notes,
            'notes' => $this->notes,
            'total_target_price' => $this->totalTargetPrice(),
            'total_offered_price' => $this->totalOfferedPrice(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            RfqStatus::SUBMITTED => 'Submitted',
            RfqStatus::REVIEWED => 'Under Review',
            RfqStatus::APPROVED => 'Approved',
            RfqStatus::REJECTED => 'Rejected',
            RfqStatus::CONVERTED_TO_ORDER => 'Converted to Order',
            RfqStatus::QUOTED => 'Quoted',
            RfqStatus::CANCELLED => 'Cancelled',
            default => $this->status->value,
        };
    }

    private function totalTargetPrice(): ?float
    {
        if (! $this->relationLoaded('items')) {
            return null;
        }

        return $this->items->sum(function ($item) {
            return ($item->target_price ?? 0) * ($item->quantity ?? 0);
        });
    }

    private function totalOfferedPrice(): ?float
    {
        if (! $this->relationLoaded('items')) {
            return null;
        }

        return $this->items->sum(function ($item) {
            return ($item->negotiated_price ?? 0) * ($item->quantity ?? 0);
        });
    }
}
