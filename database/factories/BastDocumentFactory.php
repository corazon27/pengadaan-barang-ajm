<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BastStatus;
use App\Models\BastDocument;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BastDocument>
 */
class BastDocumentFactory extends Factory
{
    protected $model = BastDocument::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'bast_number' => 'BAST-'.strtoupper(Str::random(10)),
            'status' => BastStatus::PENDING_SIGNATURE,
            'bast_document_url' => 'https://example.com/bast/'.Str::uuid().'.pdf',
            'signed_date' => now()->toDateString(),
        ];
    }
}
