<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The orders.create migration declares `foreignUuid('rfq_id')->unique()`,
     * but when `->constrained()` is chained Laravel never emits the unique
     * index on MySQL/PostgreSQL — only the foreign key is created. The result
     * is that the "one order per RFQ" invariant was not actually enforced at
     * the database level, silently weakening the TOCTOU safety net described
     * in OrderController::store.
     *
     * This migration adds the missing unique index on both engines. It is
     * idempotent: environments that already have the index are left untouched.
     *
     * NOTE: fails if existing data already contains duplicate orders for the
     * same RFQ; none exist in the current database.
     */
    public function up(): void
    {
        if (! $this->hasUniqueRfqIndex()) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unique('rfq_id');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasUniqueRfqIndex()) {
            Schema::table('orders', function (Blueprint $table) {
                // MySQL refuses to drop the unique index while it still backs
                // the orders.rfq_id foreign key, so release the FK first and
                // restore it afterwards to keep the rollback reversible.
                $table->dropForeign(['rfq_id']);
                $table->dropUnique('orders_rfq_id_unique');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('rfq_id')->references('id')->on('rfqs')->nullOnDelete();
            });
        }
    }

    private function hasUniqueRfqIndex(): bool
    {
        return collect(Schema::getIndexes('orders'))
            ->contains(fn (array $index) => $index['unique'] && in_array('rfq_id', $index['columns'], true));
    }
};
