<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive Compliance Kernel table: maps a USER/PERSON to a legal
     * function (NOT an RBAC role). Organizational role must never be inferred
     * as a legal function; this table is intentionally left empty unless
     * configured from real appointment data. No production seeding.
     */
    public function up(): void
    {
        Schema::create('legal_function_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('function_code');
            $table->string('function_category');
            $table->string('statutory_basis')->nullable();
            $table->string('appointment_basis')->nullable();
            $table->string('applicability_status')->default('PENDING_LEGAL_REVIEW');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('scope')->nullable();
            $table->string('status')->default('INACTIVE');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('user_id');
            $table->index('function_code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_function_assignments');
    }
};
