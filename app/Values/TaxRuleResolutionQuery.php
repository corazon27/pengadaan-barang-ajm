<?php

declare(strict_types=1);

namespace App\Values;

use App\Enums\BuyerClassification;
use App\Enums\TaxType;
use App\Enums\VatCollectorStatus;
use Illuminate\Support\Carbon;

/**
 * Immutable transaction context used to resolve a tax rule.
 *
 * A NULL constraint on the rule acts as a wildcard; a NULL value here means
 * the dimension is unknown for the transaction and cannot match a rule that
 * constrains it.
 */
final readonly class TaxRuleResolutionQuery
{
    public function __construct(
        public TaxType $taxType,
        public Carbon $effectiveDate,
        public ?string $taxpayerStatus = null,
        public ?BuyerClassification $buyerClassification = null,
        public ?VatCollectorStatus $vatCollectorStatus = null,
        public ?string $transactionType = null,
        public ?string $productClassification = null,
    ) {}
}
