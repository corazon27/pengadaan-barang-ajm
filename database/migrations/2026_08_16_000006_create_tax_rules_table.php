<?php

declare(strict_types=1);

use App\Enums\BuyerClassification;
use App\Enums\DppMethod;
use App\Enums\TaxApplicability;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive Phase 2A table: deterministic, versioned, source-cited tax rule
     * registry (Rulebook TAX-PPN-01/02, database_schema.md §H).
     *
     * TaxCalculationService MUST evaluate data from this registry — rates are
     * DATA (statutory_rate, effective_burden), never hardcoded code constants.
     * rule_code + rule_version are unique; rules are effective-dated
     * (effective_from/effective_until, MySQL-only CHECK of the range).
     * faktur_code references the canonical faktur_codes catalog (approved FK,
     * correction Q2). taxpayer_status and verification_status are separate
     * fields (correction Q4): value vs verification state.
     */
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rule_code', 50);
            $table->string('rule_version', 50);
            $table->enum('tax_type', array_column(TaxType::cases(), 'value'));
            $table->string('taxpayer_status', 50)->nullable();
            $table->enum('verification_status', array_column(VerificationStatus::cases(), 'value'))->nullable();
            $table->enum('buyer_classification', array_column(BuyerClassification::cases(), 'value'))->nullable();
            $table->enum('vat_collector_status', array_column(VatCollectorStatus::cases(), 'value'))->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('product_classification')->nullable();
            $table->string('base_amount_definition')->nullable();
            $table->enum('dpp_method', array_column(DppMethod::cases(), 'value'));
            $table->text('dpp_formula')->nullable();
            $table->decimal('statutory_rate', 8, 4)->nullable();
            $table->text('tax_formula')->nullable();
            $table->decimal('effective_burden', 8, 4)->nullable();
            $table->string('faktur_code', 2)->nullable();
            $table->text('withholding_rule')->nullable();
            $table->string('legal_reference', 255);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('source_version')->nullable();
            $table->date('verification_date')->nullable();
            $table->enum('applicability', array_column(TaxApplicability::cases(), 'value'))
                ->default(TaxApplicability::UNRESOLVED->value);
            $table->timestamps();

            $table->unique(['rule_code', 'rule_version']);
            $table->index('faktur_code');
            $table->index(['rule_code', 'effective_from']);

            $table->foreign('faktur_code')->references('code')->on('faktur_codes');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tax_rules ADD CONSTRAINT tax_rules_effective_range CHECK (effective_until IS NULL OR effective_until >= effective_from)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tax_rules DROP CONSTRAINT tax_rules_effective_range');
        }

        Schema::dropIfExists('tax_rules');
    }
};
