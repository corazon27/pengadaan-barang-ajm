<?php

declare(strict_types=1);

use App\Enums\BreachQualificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_registers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('incident_type', array_column(IncidentType::cases(), 'value'))->nullable();
            $table->enum('severity', array_column(IncidentSeverity::cases(), 'value'))->nullable();
            $table->enum('status', array_column(IncidentStatus::cases(), 'value'))->default(IncidentStatus::DETECTED->value);
            $table->enum('breach_qualification_status', array_column(BreachQualificationStatus::cases(), 'value'))->default(BreachQualificationStatus::UNKNOWN->value);
            $table->string('title');
            $table->text('description');
            $table->json('affected_systems')->nullable();
            $table->json('affected_data_categories')->nullable();
            $table->unsignedInteger('number_of_subjects_known')->nullable();
            $table->string('containment_status', 50)->nullable();
            $table->json('evidence_snapshot')->nullable();
            $table->uuid('human_review_case_id')->nullable();
            $table->timestamp('breach_qualified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->uuid('actor_user_id')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['incident_type']);
            $table->index(['breach_qualification_status']);
            $table->index(['severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_registers');
    }
};
