<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Relax the orders.top_days CHECK constraint so IMMEDIATE payment terms
     * (0 days, PaymentTerm::IMMEDIATE) are accepted at the database level.
     *
     * SQLite cannot ALTER TABLE ... DROP/ADD CONSTRAINT, so this only runs on
     * MySQL/PostgreSQL. The earlier positive-only constraint silently rejected
     * valid IMMEDIATE orders whenever the schema was applied to MySQL.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_top_days_positive');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_top_days_non_negative CHECK (top_days >= 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_top_days_non_negative');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_top_days_positive CHECK (top_days > 0)');
    }
};
