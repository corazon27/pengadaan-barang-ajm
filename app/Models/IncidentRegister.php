<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BreachQualificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentRegister extends Model
{
    use HasUuids;

    protected $fillable = [
        'incident_type',
        'severity',
        'status',
        'breach_qualification_status',
        'title',
        'description',
        'affected_systems',
        'affected_data_categories',
        'number_of_subjects_known',
        'containment_status',
        'evidence_snapshot',
        'human_review_case_id',
        'breach_qualified_at',
        'resolved_at',
        'closed_at',
        'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'incident_type' => IncidentType::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'breach_qualification_status' => BreachQualificationStatus::class,
            'affected_systems' => 'array',
            'affected_data_categories' => 'array',
            'number_of_subjects_known' => 'integer',
            'evidence_snapshot' => 'array',
            'breach_qualified_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IncidentRegister $incident) {
            $incident->status ??= IncidentStatus::DETECTED;
            $incident->breach_qualification_status ??= BreachQualificationStatus::UNKNOWN;
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function humanReviewCase(): BelongsTo
    {
        return $this->belongsTo(HumanReviewCase::class, 'human_review_case_id');
    }

    public function breachNotifications(): HasMany
    {
        return $this->hasMany(BreachNotification::class, 'incident_id');
    }

    public function isWorkflowConfirmed(): bool
    {
        return $this->status === IncidentStatus::CONFIRMED;
    }
}
