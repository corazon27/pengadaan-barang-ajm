<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce at most one invoice per order. Signing a BAST is the only place
     * invoices are generated; this constraint closes the race where two
     * concurrent sign requests could each see "no invoice exists" and create
     * duplicate invoices for the same order.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('order_id', 'invoices_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_order_unique');
        });
    }
};
