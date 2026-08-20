<?php

use App\Enums\DsrChannel;
use App\Enums\DsrStatus;
use App\Enums\SubjectType;
use App\Enums\VerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_subject_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subject_user_id')->nullable()->index();
            $table->enum('subject_type', array_column(SubjectType::cases(), 'value'));
            $table->string('right_code', 30);
            $table->enum('channel', array_column(DsrChannel::cases(), 'value'));
            $table->json('request_input')->nullable();
            $table->enum('identity_verification_status', array_column(VerificationStatus::cases(), 'value'))->default(VerificationStatus::UNVERIFIED->value);
            $table->string('identity_confidence', 40)->default('AUTHENTICATED_ONLY');
            $table->json('identity_verification_meta')->nullable();
            $table->json('processing_lawful_basis_evaluated')->nullable();
            $table->enum('status', array_column(DsrStatus::cases(), 'value'))->default(DsrStatus::RECEIVED->value);
            $table->enum('applicability_status', ['CONFIRMED', 'REVIEW_REQUIRED', 'UNRESOLVED', 'PENDING_LEGAL_REVIEW', 'APPLICABILITY_UNKNOWN'])->default('APPLICABILITY_UNKNOWN');
            $table->uuid('handled_by')->nullable()->index();
            $table->uuid('human_review_case_id')->nullable()->index();
            $table->string('decision_notes', 500)->nullable();
            $table->timestamp('internal_sla_target_at')->nullable()->index();
            $table->timestamps();

            $table->index(['subject_user_id', 'status']);
            $table->index(['right_code', 'status']);
            $table->index(['status', 'internal_sla_target_at']);
            $table->index(['channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_subject_requests');
    }
};
