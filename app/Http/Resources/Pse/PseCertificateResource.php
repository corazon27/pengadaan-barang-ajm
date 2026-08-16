<?php

declare(strict_types=1);

namespace App\Http\Resources\Pse;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PseCertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'psre_provider' => $this->psre_provider,
            'issued_at' => $this->issued_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'certificate_status' => $this->certificate_status?->value,
            'verification_status' => $this->verification_status?->value,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
