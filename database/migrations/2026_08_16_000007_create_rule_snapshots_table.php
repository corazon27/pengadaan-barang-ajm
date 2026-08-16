<?php

declare(strict_types=1);

use App\Enums\BuyerClassification;
use App\Enums\TaxResolutionState;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2C: authoritative per-line-item tax resolution record.
     *
     * IMMUTABLE, self-contained historical reconstruction of why a TaxRule
     * applied to a transaction at the authoritative tax event. Per line item
     * (order_item_id NOT NULL — no non-line-item use case is approved). The
     * snapshot copies the resolved rule's identifying/effective data plus the
     * transactional dimensions used by TaxRuleResolver (taxpayer_status,
     * buyer_classification, vat_collector_status, transaction_type,
     * product_classification) and the order-time rule identity
     * (order_time_rule_id canonical + code/version audit metadata).
     *
     * dpp_amount stays NULL in Phase 2C (no tax math yet — Phase 2D).
     */
    public function up(): void
    {
        Schema::create('rule_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_item_id')->constrained('order_items');
            $table->foreignUuid('tax_rule_id')->constrained('tax_rules');
            $table->string('rule_code', 50);
            $table->string('rule_version', 50);
            $table->enum('tax_type', array_column(TaxType::cases(), 'value'));
            $table->string('taxpayer_status', 50)->nullable();
            $table->enum('buyer_classification', array_column(BuyerClassification::cases(), 'value'))->nullable();
            $table->enum('vat_collector_status', array_column(VatCollectorStatus::cases(), 'value'))->nullable();
            $table->string('transaction_type', 100)->nullable();
            $table->string('product_classification', 100)->nullable();
            $table->date('resolution_date');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->decimal('dpp_amount', 15, 2)->nullable();
            $table->decimal('statutory_rate_snapshot', 8, 4)->nullable();
            $table->text('tax_formula_snapshot')->nullable();
            $table->decimal('effective_burden_snapshot', 8, 4)->nullable();
            $table->string('faktur_code', 2)->nullable();
            $table->text('withholding_snapshot')->nullable();
            $table->string('legal_reference', 255)->nullable();
            $table->foreignUuid('order_time_rule_id')->nullable()->constrained('tax_rules');
            $table->string('order_time_rule_code', 50)->nullable();
            $table->string('order_time_rule_version', 50)->nullable();
            $table->enum('resolution_state', array_column(TaxResolutionState::cases(), 'value'))
                ->default(TaxResolutionState::UNRESOLVED->value);
            $table->timestamp('created_at')->useCurrent();

            $table->index('tax_rule_id');
            $table->index('order_time_rule_id');
            $table->index(['order_item_id', 'resolution_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_snapshots');
    }
};
