<?php

declare(strict_types=1);

use App\Enums\BreachNotificationStatus;
use App\Enums\BreachNotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breach_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('incident_id');
            $table->enum('notification_type', array_column(BreachNotificationType::cases(), 'value'));
            $table->string('recipient');
            $table->enum('status', array_column(BreachNotificationStatus::cases(), 'value'))->default(BreachNotificationStatus::PENDING->value);
            $table->json('content_snapshot')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->uuid('actor_user_id')->nullable();
            $table->timestamps();

            $table->index(['incident_id']);
            $table->index(['status']);
            $table->unique(['incident_id', 'notification_type']);

            $table->foreign('incident_id')->references('id')->on('incident_registers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breach_notifications');
    }
};
