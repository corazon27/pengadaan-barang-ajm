<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BuyerClassification;
use App\Enums\DppMethod;
use App\Enums\TaxApplicability;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TaxRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'rule_code',
        'rule_version',
        'tax_type',
        'taxpayer_status',
        'verification_status',
        'buyer_classification',
        'vat_collector_status',
        'transaction_type',
        'product_classification',
        'base_amount_definition',
        'dpp_method',
        'dpp_formula',
        'statutory_rate',
        'tax_formula',
        'effective_burden',
        'faktur_code',
        'withholding_rule',
        'legal_reference',
        'effective_from',
        'effective_until',
        'source_version',
        'verification_date',
        'applicability',
    ];

    protected function casts(): array
    {
        return [
            'tax_type' => TaxType::class,
            'verification_status' => VerificationStatus::class,
            'buyer_classification' => BuyerClassification::class,
            'vat_collector_status' => VatCollectorStatus::class,
            'dpp_method' => DppMethod::class,
            'statutory_rate' => 'decimal:4',
            'effective_burden' => 'decimal:4',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'verification_date' => 'date',
            'applicability' => TaxApplicability::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TaxRule $rule) {
            $rule->applicability ??= TaxApplicability::UNRESOLVED;
        });

        static::saving(function (TaxRule $rule) {
            if ($rule->effective_until !== null && $rule->effective_until->lt($rule->effective_from)) {
                throw new InvalidArgumentException('effective_until must be on or after effective_from');
            }

            if ($rule->dpp_method === DppMethod::LAINNYA && $rule->applicability === TaxApplicability::CONFIRMED) {
                throw new InvalidArgumentException('LAINNYA DPP method requires REVIEW_REQUIRED (or stricter) applicability until a source-cited deterministic rule exists');
            }
        });
    }

    public function fakturCode(): BelongsTo
    {
        return $this->belongsTo(FakturCode::class, 'faktur_code', 'code');
    }

    public function ruleSnapshots(): HasMany
    {
        return $this->hasMany(RuleSnapshot::class);
    }

    /**
     * Whether this rule version is in force on the given date (inclusive bounds).
     */
    public function isEffectiveOn(Carbon $date): bool
    {
        if ($date->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_until !== null && $date->gt($this->effective_until)) {
            return false;
        }

        return true;
    }
}
