<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive Compliance Kernel table: the SINGLE canonical human-review
     * abstraction. All review types (TAX, SUPPLIER_ELIGIBILITY, PDP, ...)
     * flow through this table; no parallel review engines exist.
     */
    public function up(): void
    {
        Schema::create('human_review_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('rule_id');
            $table->string('trigger');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('evidence_snapshot')->nullable();
            $table->string('capability_required')->nullable();
            $table->string('legal_function_required')->nullable();
            $table->string('decision')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('PENDING');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('re_review_at')->nullable();

            $table->index('type');
            $table->index('rule_id');
            $table->index('status');
            $table->index('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_review_cases');
    }
};
