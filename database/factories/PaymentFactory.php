<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 1000, 10000000),
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'payment_date' => now()->toDateString(),
            'proof_file_url' => 'https://example.com/payments/proofs/'.Str::uuid().'.jpg',
            'notes' => null,
            'status' => PaymentStatus::PENDING_VERIFICATION,
            'verified_by' => null,
            'verified_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::PENDING_VERIFICATION,
            'verified_by' => null,
            'verified_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::VERIFIED,
            'verified_by' => User::factory(),
            'verified_at' => now(),
        ]);
    }
}
