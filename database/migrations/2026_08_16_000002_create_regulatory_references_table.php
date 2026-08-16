<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive Compliance Kernel table: stores approved regulatory metadata
     * (source references, effective dates, source version) that later
     * submodules resolve against. This table is intentionally NOT linked to
     * audit_logs; the existing Module 8 AuditLogger remains the sole audit
     * substrate (Phase 1 correction).
     */
    public function up(): void
    {
        Schema::create('regulatory_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_version');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();

            $table->index('status');
            $table->index('effective_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_references');
    }
};
