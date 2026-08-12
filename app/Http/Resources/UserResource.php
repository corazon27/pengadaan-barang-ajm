<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'role' => $this->role instanceof UserRole ? $this->role->value : $this->role,
            'company_name' => $this->company_name,
            'npwp_number' => $this->npwp_number,
            'address' => $this->address,
            'phone_number' => $this->phone_number,
        ];
    }
}
