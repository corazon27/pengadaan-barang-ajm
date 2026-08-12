<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'full_name' => fake()->name(),
            'company_name' => fake()->company(),
            'npwp_number' => fake()->numerify('##.###.###.#-###.###'),
            'address' => fake()->address(),
            'phone_number' => fake()->phoneNumber(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['role' => UserRole::BUYER_B2B])->save());
    }

    public function superadmin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['role' => UserRole::SUPERADMIN])->save());
    }

    public function buyerB2b(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['role' => UserRole::BUYER_B2B])->save());
    }

    public function buyerB2g(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['role' => UserRole::BUYER_B2G])->save());
    }
}
