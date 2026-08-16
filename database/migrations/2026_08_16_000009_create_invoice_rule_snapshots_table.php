<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2C: invoice ↔ RuleSnapshot junction.
     *
     * One invoice carries MANY RuleSnapshot (per line item). This table is the
     * relationship only — no second source-of-truth order_item link (canonical
     * path: Invoice ↕ InvoiceRuleSnapshot ↕ RuleSnapshot ↓ OrderItem).
     * tax_amount stays NULL in Phase 2C (no tax math — Phase 2D).
     */
    public function up(): void
    {
        Schema::create('invoice_rule_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices');
            $table->foreignUuid('rule_snapshot_id')->constrained('rule_snapshots');
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['invoice_id', 'rule_snapshot_id']);
            $table->index('rule_snapshot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_rule_snapshots');
    }
};
