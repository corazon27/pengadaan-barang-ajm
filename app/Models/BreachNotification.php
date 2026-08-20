<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BreachNotificationStatus;
use App\Enums\BreachNotificationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreachNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'incident_id',
        'notification_type',
        'recipient',
        'status',
        'content_snapshot',
        'prepared_at',
        'sent_at',
        'confirmed_at',
        'failed_at',
        'failure_reason',
        'evidence_reference',
        'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'notification_type' => BreachNotificationType::class,
            'status' => BreachNotificationStatus::class,
            'content_snapshot' => 'array',
            'prepared_at' => 'datetime',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BreachNotification $notification) {
            $notification->status ??= BreachNotificationStatus::PENDING;
        });
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(IncidentRegister::class, 'incident_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
