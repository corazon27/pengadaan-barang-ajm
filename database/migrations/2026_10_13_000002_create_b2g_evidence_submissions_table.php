<?php

declare(strict_types=1);

use App\Enums\B2gEvidenceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned B2G evidence submissions. This table is the authoritative source for
 * evidence metadata, versions, and review history; there is no redundant copy on
 * `users`. The unique (user_id, version) constraint plus transactional version
 * allocation prevents duplicate/concurrent submissions from producing the same
 * version number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2g_evidence_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 50)->default('documents');
            $table->string('path');
            $table->string('original_filename', 255);
            $table->string('mime', 150);
            $table->unsignedBigInteger('size');
            $table->string('evidence_type', 100);
            $table->string('institution_name', 200)->nullable();
            $table->string('work_unit', 200)->nullable();
            $table->string('position', 200)->nullable();
            $table->string('reference', 500)->nullable();
            $table->string('status', 20)->default(B2gEvidenceStatus::SUBMITTED->value);
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'version']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2g_evidence_submissions');
    }
};
