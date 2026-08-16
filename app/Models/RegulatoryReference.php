<?php

declare(strict_types=1);

namespace App\Models;

use App\Events\RegulatoryReferenceCreated;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegulatoryReference extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'reference_code',
        'title',
        'description',
        'effective_from',
        'effective_to',
        'source_version',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RegulatoryReference $reference) {
            $reference->status ??= 'ACTIVE';
        });

        static::created(function (RegulatoryReference $reference) {
            RegulatoryReferenceCreated::dispatch($reference);
        });
    }
}
