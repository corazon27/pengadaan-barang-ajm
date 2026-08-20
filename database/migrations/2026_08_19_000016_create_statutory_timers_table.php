<?php

declare(strict_types=1);

use App\Enums\StatutoryTimerEnforcement;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Enums\ViolationState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_timers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('timer_type', array_column(StatutoryTimerType::cases(), 'value'));
            $table->enum('enforcement', array_column(StatutoryTimerEnforcement::cases(), 'value'));
            $table->string('ref_type', 50)->nullable();
            $table->uuid('ref_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('deadline_at');
            $table->enum('status', array_column(StatutoryTimerStatus::cases(), 'value'))->default(StatutoryTimerStatus::RUNNING->value);
            $table->enum('violation_state', array_column(ViolationState::cases(), 'value'))->nullable();
            $table->uuid('breach_notification_id')->nullable();
            $table->timestamps();

            $table->index(['deadline_at', 'status']);
            $table->index(['enforcement', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_timers');
    }
};
