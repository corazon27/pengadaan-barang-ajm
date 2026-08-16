<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FakturCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => '01',
            'description' => 'Faktur pajak untuk penyerahan BKP/JKP pada umumnya (default)',
            'required_buyer_class' => null,
            'required_collector_status' => null,
            'effective_from' => '2025-02-04',
            'effective_until' => null,
        ];
    }
}
