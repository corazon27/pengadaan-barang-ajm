<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VatCollectorStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FakturCode extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'description',
        'required_buyer_class',
        'required_collector_status',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'required_collector_status' => VatCollectorStatus::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function taxRules(): HasMany
    {
        return $this->hasMany(TaxRule::class, 'faktur_code', 'code');
    }
}
