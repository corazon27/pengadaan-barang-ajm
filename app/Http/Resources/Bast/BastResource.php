<?php

declare(strict_types=1);

namespace App\Http\Resources\Bast;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BastResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bast_number' => $this->bast_number,
            'order_id' => $this->order_id,
            'status' => $this->status?->value,
            'status_label' => $this->status ? $this->statusLabel() : null,
            'bast_document_url' => $this->bast_document_url,
            'signed_by' => $this->whenLoaded('signedBy', fn () => new UserResource($this->signedBy)),
            'signed_at' => $this->signed_at?->toISOString(),
            'signed_date' => $this->signed_date?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
