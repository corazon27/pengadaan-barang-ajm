<?php

declare(strict_types=1);

use App\Enums\DppMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2D: make RuleSnapshot self-contained for historical monetary
     * calculation.
     *
     * Adds the rule data needed to compute DPP (dpp_method, dpp_formula,
     * base_amount_definition) plus the immutable monetary inputs captured at
     * resolution time (unit_price, quantity, line_base_amount). Existing rows
     * keep NULL in these columns and are treated as INCOMPLETE_RULE_DATA by
     * TaxCalculationService — never backfilled or invented.
     */
    public function up(): void
    {
        Schema::table('rule_snapshots', function (Blueprint $table) {
            $table->enum('dpp_method_snapshot', array_column(DppMethod::cases(), 'value'))
                ->nullable()
                ->after('dpp_amount');
            $table->text('dpp_formula_snapshot')->nullable()->after('dpp_method_snapshot');
            $table->string('base_amount_definition_snapshot', 100)->nullable()->after('dpp_formula_snapshot');
            $table->decimal('unit_price_snapshot', 15, 2)->nullable()->after('base_amount_definition_snapshot');
            $table->unsignedInteger('quantity_snapshot')->nullable()->after('unit_price_snapshot');
            $table->decimal('line_base_amount_snapshot', 15, 2)->nullable()->after('quantity_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('rule_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'dpp_method_snapshot',
                'dpp_formula_snapshot',
                'base_amount_definition_snapshot',
                'unit_price_snapshot',
                'quantity_snapshot',
                'line_base_amount_snapshot',
            ]);
        });
    }
};
