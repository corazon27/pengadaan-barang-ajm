<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive Compliance Kernel table: processor/counterparty governance.
     * Stores processor relationship evidence and role determinations under
     * PDP-PROC-001. Two independent state machines: processor_role_status
     * (UNKNOWN/REQUIRE_REVIEW/PROCESSOR/NOT_PROCESSOR) and agreement_status
     * (DRAFT/ACTIVE/SUSPENDED/EXPIRED/TERMINATED).
     */
    public function up(): void
    {
        Schema::create('processor_agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('counterparty_name');
            $table->json('counterparty_identifier')->nullable();
            $table->string('processor_role_status')->default('UNKNOWN');
            $table->string('agreement_status')->default('DRAFT');
            $table->json('data_categories')->nullable();
            $table->json('processing_purposes')->nullable();
            $table->string('legal_basis')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->boolean('evidence_verified')->default(false);
            $table->timestamp('evidence_verified_at')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->uuid('human_review_case_id')->nullable();
            $table->uuid('actor_user_id');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('processor_role_status');
            $table->index('agreement_status');
            $table->index('counterparty_name');
            $table->index('actor_user_id');
            $table->index('human_review_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processor_agreements');
    }
};
