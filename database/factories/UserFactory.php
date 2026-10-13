<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        // Factory users model trusted, operational accounts (matching pre-lifecycle
        // behaviour) so the existing suite keeps exercising authenticated surfaces.
        // Tests that need PENDING/REJECTED/SUSPENDED states set status explicitly.
        return $this->afterCreating(fn (User $user) => $user->forceFill([
            'role' => UserRole::BUYER_B2B,
            'status' => UserStatus::ACTIVE,
        ])->save());
    }

    public function pending(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['status' => UserStatus::PENDING])->save());
    }

    public function suspended(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['status' => UserStatus::SUSPENDED])->save());
    }

    public function rejected(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill(['status' => UserStatus::REJECTED])->save());
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
