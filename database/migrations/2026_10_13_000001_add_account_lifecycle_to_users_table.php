<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the account access lifecycle to `users` using a NULL sentinel so the
 * legacy-grandfathering backfill is unambiguous and retry-safe.
 *
 * Order (see plan): add `status` nullable WITHOUT a default (pre-existing rows
 * become NULL), backfill NULL rows to ACTIVE, then establish NOT NULL with
 * PENDING as the default for new rows. Because the backfill only matches
 * `status IS NULL`, a re-run after a partial failure can never flip a newly
 * registered PENDING account to ACTIVE. Existing `email_verified_at` values are
 * preserved untouched; no verification timestamps are fabricated.
 *
 * MySQL DDL is not fully transactional, so a partially applied migration is
 * possible; the statements below are individually idempotent-safe when re-run.
 * Production rollout still requires a maintenance window, a backup, and
 * quiesced account writes (registered operational precondition).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->after('role');
            $table->string('status_reason', 500)->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            $table->string('requested_role', 30)->nullable()->after('status_changed_at');
        });

        // Grandfather every pre-migration row. Only NULL rows can be legacy rows,
        // so this never touches a row created after the column was added.
        DB::table('users')->whereNull('status')->update(['status' => UserStatus::ACTIVE->value]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->nullable(false)->default(UserStatus::PENDING->value)->change();
            $table->index('status');
        });
    }

    public function down(): void
    {
        // WARNING: dropping these columns destroys recorded lifecycle state.
        // After any real account decision, fix forward instead of rolling back.
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'status_reason', 'status_changed_at', 'requested_role']);
        });
    }
};
