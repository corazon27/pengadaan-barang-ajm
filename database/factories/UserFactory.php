<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'full_name' => fake()->name(),
            'role' => UserRole::BUYER_B2B->value,
            'company_name' => fake()->company(),
            'npwp_number' => fake()->numerify('##.###.###.#-###.###'),
            'address' => fake()->address(),
            'phone_number' => fake()->phoneNumber(),
        ];
    }

    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SUPERADMIN->value,
            'company_name' => 'PT Anugerah Jaya Mandiri (Internal)',
        ]);
    }

    public function buyerB2b(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::BUYER_B2B->value,
        ]);
    }

    public function buyerB2g(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::BUYER_B2G->value,
            'company_name' => 'Dinas Komunikasi dan Informatika',
        ]);
    }
}
