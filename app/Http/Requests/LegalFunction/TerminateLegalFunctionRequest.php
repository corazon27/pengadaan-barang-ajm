<?php

declare(strict_types=1);

namespace App\Http\Requests\LegalFunction;

use Illuminate\Foundation\Http\FormRequest;

class TerminateLegalFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
