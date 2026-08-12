<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\BastDocument;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'bast_id' => fn (array $attributes) => BastDocument::factory()
                ->create(['order_id' => $attributes['order_id']])
                ->id,
            'invoice_number' => 'INV-'.strtoupper(Str::random(10)),
            'faktur_pajak_number' => null,
            'invoice_pdf_url' => 'https://example.com/invoices/'.Str::uuid().'.pdf',
            'faktur_pajak_url' => null,
            'amount_due' => fn (array $attributes) => Order::find($attributes['order_id'])?->total_amount ?? 0,
            'issued_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => InvoiceStatus::UNPAID,
            'paid_at' => null,
        ];
    }
}
