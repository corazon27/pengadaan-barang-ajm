<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Apply database-level CHECK constraints for financial and inventory integrity.
     *
     * SQLite (the default local/test driver) cannot ALTER TABLE ... ADD CONSTRAINT,
     * so these are skipped there; unsigned columns above still prevent negatives.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_margin_range CHECK (margin_percentage BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_tax_rate_range CHECK (tax_rate_percentage BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_tkdn_range CHECK (tkdn_percentage IS NULL OR tkdn_percentage BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_non_negative CHECK (stock >= 0)');
        DB::statement('ALTER TABLE rfq_items ADD CONSTRAINT rfq_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_top_days_positive CHECK (top_days > 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_due_after_issued CHECK (due_date >= issued_date)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE products DROP CONSTRAINT products_margin_range');
        DB::statement('ALTER TABLE products DROP CONSTRAINT products_tax_rate_range');
        DB::statement('ALTER TABLE products DROP CONSTRAINT products_tkdn_range');
        DB::statement('ALTER TABLE products DROP CONSTRAINT products_stock_non_negative');
        DB::statement('ALTER TABLE rfq_items DROP CONSTRAINT rfq_items_quantity_positive');
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT order_items_quantity_positive');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_top_days_positive');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_due_after_issued');
    }
};
