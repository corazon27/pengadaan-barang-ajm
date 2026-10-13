<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Provision the first (or an additional) SUPERADMIN from the CLI.
 *
 * This is intentionally CLI-only and not reachable over HTTP: it creates an
 * already-active, already-verified privileged account, bypassing the public
 * registration/approval flow. The provisioning is strictly audited.
 */
class CreateSuperAdminCommand extends Command
{
    protected $signature = 'ajm:create-superadmin
        {--email= : Email address of the superadmin}
        {--name= : Full name of the superadmin}
        {--company= : Company or work-unit label (defaults to an internal label)}
        {--address= : Contact address (defaults to a placeholder)}
        {--phone= : Contact phone number (defaults to a placeholder)}
        {--password= : Password (omit to be prompted securely)}';

    protected $description = 'Create an active SUPERADMIN account (CLI only)';

    public function handle(AuditLogger $auditLogger): int
    {
        $email = (string) ($this->option('email') ?: $this->ask('Email'));
        $name = (string) ($this->option('name') ?: $this->ask('Nama lengkap'));
        // Internal operator accounts have no commercial company/address; the
        // schema requires these columns, so sensible placeholders are used.
        $company = (string) ($this->option('company') ?: 'AKUN INTERNAL');
        $address = (string) ($this->option('address') ?: '-');
        $phone = (string) ($this->option('phone') ?: '-');
        $password = (string) ($this->option('password') ?: $this->secret('Kata sandi (minimal 12 karakter)'));

        $validator = Validator::make(
            [
                'email' => $email,
                'full_name' => $name,
                'company_name' => $company,
                'address' => $address,
                'phone_number' => $phone,
                'password' => $password,
            ],
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'full_name' => ['required', 'string', 'max:255'],
                'company_name' => ['required', 'string', 'max:200'],
                'address' => ['required', 'string'],
                'phone_number' => ['required', 'string', 'max:20'],
                // Deliberate policy difference: privileged accounts require a
                // longer password (12) than public self-service registration (8).
                'password' => ['required', 'string', 'min:12'],
            ],
            [
                'email.unique' => 'Email sudah terdaftar.',
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($email, $name, $company, $address, $phone, $password, $auditLogger): User {
            $user = new User([
                'email' => $email,
                'full_name' => $name,
                'company_name' => $company,
                'address' => $address,
                'phone_number' => $phone,
                'password' => $password,
            ]);
            $user->role = UserRole::SUPERADMIN;
            $user->status = UserStatus::ACTIVE;
            $user->status_reason = 'Dibuat melalui perintah CLI ajm:create-superadmin.';
            $user->status_changed_at = now();
            $user->email_verified_at = now();
            $user->save();

            $auditLogger->strictLog($user, AuditAction::SUPERADMIN_PROVISIONED, $user, [], [
                'email' => $user->email,
                'role' => UserRole::SUPERADMIN->value,
                'status' => UserStatus::ACTIVE->value,
                'provisioned_via' => 'cli',
            ]);

            return $user;
        });

        $this->info("Superadmin berhasil dibuat: {$user->email}");

        return self::SUCCESS;
    }
}
