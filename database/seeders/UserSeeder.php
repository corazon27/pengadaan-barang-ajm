<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Account Superadmin (Internal Vendor)
        User::updateOrCreate(
            ['email' => 'admin@ajm.co.id'],
            [
                'password' => Hash::make('password123'),
                'full_name' => 'Super Administrator AJM',
                'role' => UserRole::SUPERADMIN,
                'company_name' => 'PT Anugerah Jaya Mandiri',
                'npwp_number' => '01.234.567.8-901.000',
                'address' => 'Jl. Jendral Sudirman No. 45, Jakarta Selatan',
                'phone_number' => '021-5550192',
            ]
        );

        // 2. Account Buyer B2B (Perusahaan Swasta)
        User::updateOrCreate(
            ['email' => 'buyer.b2b@corporation.com'],
            [
                'password' => Hash::make('password123'),
                'full_name' => 'Budi Santoso (Procurement Manager)',
                'role' => UserRole::BUYER_B2B,
                'company_name' => 'PT Mega Sukses Perdana',
                'npwp_number' => '02.987.654.3-210.000',
                'address' => 'Kawasan Industri Jababeka Phase 2, Cikarang',
                'phone_number' => '081299887766',
            ]
        );

        // 3. Account Buyer B2G (Instansi Pemerintah)
        User::updateOrCreate(
            ['email' => 'ppk.b2g@dinas.go.id'],
            [
                'password' => Hash::make('password123'),
                'full_name' => 'Dr. Ahmad Hidayat, M.Si (PPK)',
                'role' => UserRole::BUYER_B2G,
                'company_name' => 'Dinas Pendidikan dan Kebudayaan',
                'npwp_number' => '00.111.222.3-444.000',
                'address' => 'Komplek Perkantoran Pemda Blok B No. 12',
                'phone_number' => '081122334455',
            ]
        );
    }
}
