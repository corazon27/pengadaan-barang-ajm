<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Rfq>
 */
class RfqFactory extends Factory
{
    protected $model = Rfq::class;

    public function definition(): array
    {
        return [
            'rfq_number' => 'RFQ-'.strtoupper(Str::random(10)),
            'user_id' => User::factory(),
            'quotation_pdf_url' => null,
            'notes' => null,
        ];
    }
}
