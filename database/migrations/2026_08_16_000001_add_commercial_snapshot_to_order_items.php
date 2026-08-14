<?php

use App\Models\OrderItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Capture the commercial identity of each order line at order time so a
     * later catalog change (SKU, title, tax rates) never retroactively alters
     * an approved order or its invoice. product_id stays the relational key;
     * the *_snapshot columns freeze the values that applied at order stage.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_sku_snapshot', 50)->nullable()->after('product_id');
            $table->string('product_title_snapshot')->nullable()->after('product_sku_snapshot');
            $table->decimal('ppn_rate_snapshot', 5, 2)->nullable()->after('product_title_snapshot');
            $table->decimal('pph_rate_snapshot', 5, 2)->nullable()->after('ppn_rate_snapshot');
        });

        // Backfill existing rows from the catalog so historical orders keep a
        // stable commercial identity going forward.
        OrderItem::query()->with('product')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                if ($item->product === null) {
                    continue;
                }

                $item->forceFill([
                    'product_sku_snapshot' => $item->product->sku,
                    'product_title_snapshot' => $item->product->title,
                    'ppn_rate_snapshot' => $item->product->tax_rate_percentage,
                    'pph_rate_snapshot' => $item->product->pph_rate_percentage,
                ])->saveQuietly();
            }
        });

        // The identity fields are mandatory once backfilled; tax rates may
        // legitimately be absent (0 / no withholding).
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_sku_snapshot', 50)->nullable(false)->change();
            $table->string('product_title_snapshot')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_sku_snapshot',
                'product_title_snapshot',
                'ppn_rate_snapshot',
                'pph_rate_snapshot',
            ]);
        });
    }
};
