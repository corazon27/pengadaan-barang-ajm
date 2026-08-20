<?php

use App\Enums\ConsentSourceChannel;
use App\Enums\ConsentStatus;
use App\Enums\LawfulBasis;
use App\Enums\SubjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subject_user_id')->nullable()->index();
            $table->enum('subject_type', array_column(SubjectType::cases(), 'value'));
            $table->string('purpose', 200);
            $table->enum('processing_lawful_basis', array_column(LawfulBasis::cases(), 'value'));
            $table->string('notice_version', 50);
            $table->string('document_ref');
            $table->enum('consent_status', array_column(ConsentStatus::cases(), 'value'))->default(ConsentStatus::ACTIVE->value);
            $table->timestamp('granted_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('withdrawal_deadline_at')->nullable();
            $table->enum('source_channel', array_column(ConsentSourceChannel::cases(), 'value'));
            $table->uuid('actor_user_id')->nullable();
            $table->string('evidence_reference', 255)->nullable();
            $table->string('rule_id', 50)->nullable()->index();
            $table->uuid('predecessor_consent_id')->nullable()->index();
            $table->timestamps();

            $table->index(['subject_user_id', 'consent_status']);
            $table->index(['purpose', 'consent_status']);
            $table->index(['notice_version', 'document_ref', 'granted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
