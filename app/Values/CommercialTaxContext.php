<?php

declare(strict_types=1);

namespace App\Values;

use App\Enums\BuyerClassification;
use App\Enums\VatCollectorStatus;

/**
 * Immutable commercial tax context captured at order time (provisional, Stage 1
 * of the two-stage model). Mirrors the order_items commercial snapshot columns
 * plus the order-time rule identity (canonical order_time_rule_id with
 * code/version as audit metadata).
 *
 * This is the frozen historical evidence used by TaxResolutionService at the
 * authoritative tax event. Taxpayer status is snapshotted here so resolution
 * does not depend on live PKP/user data.
 */
final readonly class CommercialTaxContext
{
    public function __construct(
        public string $unitPriceSnapshot,
        public string $lineBaseAmountSnapshot,
        public ?string $productClassification = null,
        public ?BuyerClassification $buyerClassification = null,
        public ?VatCollectorStatus $collectorStatus = null,
        public ?string $transactionType = null,
        public ?string $taxpayerStatus = null,
        public ?string $orderTimeRuleId = null,
        public ?string $orderTimeRuleCode = null,
        public ?string $orderTimeRuleVersion = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['unit_price_snapshot'] ?? '0'),
            (string) ($data['line_base_amount_snapshot'] ?? '0'),
            isset($data['product_classification_snapshot']) ? (string) $data['product_classification_snapshot'] : null,
            isset($data['buyer_classification_snapshot']) ? BuyerClassification::from((string) $data['buyer_classification_snapshot']) : null,
            isset($data['collector_status_snapshot']) ? VatCollectorStatus::from((string) $data['collector_status_snapshot']) : null,
            isset($data['transaction_type_snapshot']) ? (string) $data['transaction_type_snapshot'] : null,
            isset($data['taxpayer_status_snapshot']) ? (string) $data['taxpayer_status_snapshot'] : null,
            isset($data['order_time_rule_id']) ? (string) $data['order_time_rule_id'] : null,
            isset($data['order_time_rule_code']) ? (string) $data['order_time_rule_code'] : null,
            isset($data['order_time_rule_version']) ? (string) $data['order_time_rule_version'] : null,
        );
    }

    /**
     * Flat array keyed by the order_items snapshot columns (for persistence and
     * audit-log serialization).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'unit_price_snapshot' => $this->unitPriceSnapshot,
            'line_base_amount_snapshot' => $this->lineBaseAmountSnapshot,
            'product_classification_snapshot' => $this->productClassification,
            'buyer_classification_snapshot' => $this->buyerClassification?->value,
            'collector_status_snapshot' => $this->collectorStatus?->value,
            'transaction_type_snapshot' => $this->transactionType,
            'taxpayer_status_snapshot' => $this->taxpayerStatus,
            'order_time_rule_id' => $this->orderTimeRuleId,
            'order_time_rule_code' => $this->orderTimeRuleCode,
            'order_time_rule_version' => $this->orderTimeRuleVersion,
        ];
    }

    /**
     * Order-time rule identity as (id, code, version) tuple; all three are
     * optional but carried together as historical audit metadata.
     *
     * @return array{id: ?string, code: ?string, version: ?string}
     */
    public function orderTimeRuleIdentity(): array
    {
        return [
            'id' => $this->orderTimeRuleId,
            'code' => $this->orderTimeRuleCode,
            'version' => $this->orderTimeRuleVersion,
        ];
    }
}
