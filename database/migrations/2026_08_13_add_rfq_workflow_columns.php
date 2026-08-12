<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->timestamp('valid_until')->nullable()->after('notes');
            $table->text('admin_notes')->nullable()->after('valid_until');
        });

        Schema::table('rfq_items', function (Blueprint $table) {
            $table->decimal('target_price', 15, 2)->nullable()->after('product_id');
            $table->text('notes')->nullable()->after('target_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn(['valid_until', 'admin_notes']);
        });

        Schema::table('rfq_items', function (Blueprint $table) {
            $table->dropColumn(['target_price', 'notes']);
        });
    }
};
