<?php

declare(strict_types=1);

namespace App\Http\Resources\Pse;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PseRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pse_registration_number' => $this->pse_registration_number,
            'pse_type' => $this->pse_type,
            'registered_at' => $this->registered_at?->toDateString(),
            'maintenance_due_at' => $this->maintenance_due_at?->toDateString(),
            'registration_status' => $this->registration_status?->value,
            'applicability' => $this->applicability?->value,
            'is_registered' => $this->isRegistered(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
