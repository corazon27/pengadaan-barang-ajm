<?php

declare(strict_types=1);

namespace App\Values;

/**
 * Immutable outcome of applying the authoritative tax engine to an invoice
 * (Phase 2E).
 *
 * A resolved outcome carries the authoritative PPN amount and is committed to
 * the invoice. A hold outcome means at least one line could not be resolved or
 * calculated authoritatively: no snapshots are persisted, the invoice keeps
 * zero tax, and the caller must flip it to REVIEW_REQUIRED.
 */
final readonly class InvoiceTaxOutcome
{
    public function __construct(
        public bool $authoritative,
        public string $ppnAmount,
        public string $subtotal,
        public int $lineCount,
        public int $resolvedLineCount,
        public string $calculationVersion,
        public ?string $holdReasonCode = null,
    ) {}

    public static function resolved(
        string $ppnAmount,
        string $subtotal,
        int $lineCount,
        string $calculationVersion,
    ): self {
        return new self(
            authoritative: true,
            ppnAmount: $ppnAmount,
            subtotal: $subtotal,
            lineCount: $lineCount,
            resolvedLineCount: $lineCount,
            calculationVersion: $calculationVersion,
        );
    }

    public static function hold(
        string $subtotal,
        int $lineCount,
        int $resolvedLineCount,
        string $calculationVersion,
        string $holdReasonCode,
    ): self {
        return new self(
            authoritative: false,
            ppnAmount: '0.00',
            subtotal: $subtotal,
            lineCount: $lineCount,
            resolvedLineCount: $resolvedLineCount,
            calculationVersion: $calculationVersion,
            holdReasonCode: $holdReasonCode,
        );
    }

    public function isAuthoritative(): bool
    {
        return $this->authoritative;
    }

    /**
     * grand_total = subtotal + PPN. PPh withholding is informational only and
     * is never added to the billed amount.
     */
    public function grandTotal(): string
    {
        return bcadd($this->subtotal, $this->ppnAmount, 2);
    }
}
