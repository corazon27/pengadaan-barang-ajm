<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutoryTimerEnforcement;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Enums\ViolationState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StatutoryTimer extends Model
{
    use HasUuids;

    protected $fillable = [
        'timer_type',
        'enforcement',
        'ref_type',
        'ref_id',
        'started_at',
        'deadline_at',
        'status',
        'violation_state',
        'breach_notification_id',
    ];

    protected function casts(): array
    {
        return [
            'timer_type' => StatutoryTimerType::class,
            'enforcement' => StatutoryTimerEnforcement::class,
            'status' => StatutoryTimerStatus::class,
            'violation_state' => ViolationState::class,
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->status === StatutoryTimerStatus::RUNNING
            && $this->deadline_at !== null
            && $this->deadline_at->isPast();
    }

    public function isRunning(): bool
    {
        return $this->status === StatutoryTimerStatus::RUNNING;
    }
}
