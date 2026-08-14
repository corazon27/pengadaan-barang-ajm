<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = $this->resolvePassword('SEED_ADMIN_PASSWORD', 'password123');
        $buyerB2bPassword = $this->resolvePassword('SEED_BUYER_B2B_PASSWORD', 'password123');
        $buyerB2gPassword = $this->resolvePassword('SEED_BUYER_B2G_PASSWORD', 'password123');

        // 1. Account Superadmin (Internal Vendor)
        $superadmin = User::updateOrCreate(
            ['email' => 'admin@ajm.co.id'],
            [
                'password' => Hash::make($password),
                'full_name' => 'Super Administrator AJM',
                'company_name' => 'PT Anugerah Jaya Mandiri',
                'npwp_number' => '01.234.567.8-901.000',
                'address' => 'Jl. Jendral Sudirman No. 45, Jakarta Selatan',
                'phone_number' => '021-5550192',
            ]
        );
        $superadmin->forceFill(['role' => UserRole::SUPERADMIN])->save();

        // 2. Account Buyer B2B (Perusahaan Swasta)
        $buyerB2b = User::updateOrCreate(
            ['email' => 'buyer.b2b@corporation.com'],
            [
                'password' => Hash::make($buyerB2bPassword),
                'full_name' => 'Budi Santoso (Procurement Manager)',
                'company_name' => 'PT Mega Sukses Perdana',
                'npwp_number' => '02.987.654.3-210.000',
                'address' => 'Kawasan Industri Jababeka Phase 2, Cikarang',
                'phone_number' => '081299887766',
            ]
        );
        $buyerB2b->forceFill(['role' => UserRole::BUYER_B2B])->save();

        // 3. Account Buyer B2G (Instansi Pemerintah)
        $buyerB2g = User::updateOrCreate(
            ['email' => 'ppk.b2g@dinas.go.id'],
            [
                'password' => Hash::make($buyerB2gPassword),
                'full_name' => 'Dr. Ahmad Hidayat, M.Si (PPK)',
                'company_name' => 'Dinas Pendidikan dan Kebudayaan',
                'npwp_number' => '00.111.222.3-444.000',
                'address' => 'Komplek Perkantoran Pemda Blok B No. 12',
                'phone_number' => '081122334455',
            ]
        );
        $buyerB2g->forceFill(['role' => UserRole::BUYER_B2G])->save();
    }

    /**
     * Resolve a seeder password, failing closed in production.
     *
     * In production this seeder only runs when SEED_DEMO_USERS is explicitly
     * enabled, and every account password must be supplied via the named env
     * variable — there is no known-default fallback. Outside production the
     * deterministic default is used so local and CI setups stay reproducible.
     */
    private function resolvePassword(string $envName, string $fallback): string
    {
        if (app()->environment('production')) {
            if (config('app.demo.seed_users') !== true) {
                throw new RuntimeException(
                    'UserSeeder refused to run in production because SEED_DEMO_USERS is not enabled. '
                    .'If you intend to seed demo accounts, set SEED_DEMO_USERS=true and provide every '
                    .'account password through the environment.'
                );
            }

            $configKey = match ($envName) {
                'SEED_ADMIN_PASSWORD' => 'admin_password',
                'SEED_BUYER_B2B_PASSWORD' => 'buyer_b2b_password',
                'SEED_BUYER_B2G_PASSWORD' => 'buyer_b2g_password',
                default => throw new RuntimeException("Unhandled seeder password env var: {$envName}"),
            };

            $password = config("app.demo.{$configKey}");

            if (! is_string($password) || $password === '') {
                throw new RuntimeException(
                    "UserSeeder refused to run in production because {$envName} is not set. "
                    .'Never use a known default password in production; provide an explicit value.'
                );
            }

            return $password;
        }

        return $fallback;
    }
}
