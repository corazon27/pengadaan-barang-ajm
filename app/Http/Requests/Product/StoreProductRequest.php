<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()?->role === UserRole::SUPERADMIN ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:150', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'margin_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'estimated_shipping' => ['nullable', 'numeric', 'min:0'],
            'tkdn_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_sni' => ['nullable', 'boolean'],
            'warranty_info' => ['nullable', 'string', 'max:255'],
            'datasheet_url' => ['nullable', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
        ];
    }
}
