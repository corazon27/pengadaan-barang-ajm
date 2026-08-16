<?php

declare(strict_types=1);

use App\Enums\VatCollectorStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2C: freeze the CommercialTaxContext on order_items at order time.
     *
     * All columns nullable and no backfill: new transactions capture their own
     * context at order creation; historical rows intentionally keep NULL until
     * the responsible order-time path writes them (order workflow untouched in
     * Phase 2C). taxpayer_status_snapshot is the order-time representation of
     * the resolver's taxpayer_status input so authoritative resolution is
     * historically reproducible without relying on live PKP/user data.
     * commercial_context_frozen_at marks the freeze; once set, direct mutation
     * of the context columns is blocked by the OrderItem model guard.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price_snapshot', 15, 2)->nullable();
            $table->decimal('line_base_amount_snapshot', 15, 2)->nullable();
            $table->string('product_classification_snapshot', 100)->nullable();
            $table->string('buyer_classification_snapshot', 100)->nullable();
            $table->enum('collector_status_snapshot', array_column(VatCollectorStatus::cases(), 'value'))->nullable();
            $table->string('transaction_type_snapshot', 100)->nullable();
            $table->string('taxpayer_status_snapshot', 50)->nullable();
            $table->foreignUuid('order_time_rule_id')->nullable()->constrained('tax_rules');
            $table->string('order_time_rule_code', 50)->nullable();
            $table->string('order_time_rule_version', 50)->nullable();
            $table->timestamp('commercial_context_frozen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_time_rule_id']);

            $table->dropColumn([
                'unit_price_snapshot',
                'line_base_amount_snapshot',
                'product_classification_snapshot',
                'buyer_classification_snapshot',
                'collector_status_snapshot',
                'transaction_type_snapshot',
                'taxpayer_status_snapshot',
                'order_time_rule_id',
                'order_time_rule_code',
                'order_time_rule_version',
                'commercial_context_frozen_at',
            ]);
        });
    }
};
