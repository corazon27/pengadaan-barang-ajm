<?php

declare(strict_types=1);

use App\Enums\VatCollectorStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive Phase 2A table: canonical, stable faktur code reference catalog
     * (Rulebook TAX-PPN-02, PER-11/PJ/2025 Lampiran D).
     *
     * This is the single authoritative representation of faktur codes; the
     * approved design adds the tax_rules.faktur_code foreign key onto
     * faktur_codes.code so no second authoritative code source exists. Codes
     * are reference data: admins must not arbitrarily mutate these rows.
     */
    public function up(): void
    {
        Schema::create('faktur_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 2)->unique();
            $table->string('description');
            $table->string('required_buyer_class')->nullable();
            $table->enum('required_collector_status', array_column(VatCollectorStatus::cases(), 'value'))->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index('effective_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faktur_codes');
    }
};
