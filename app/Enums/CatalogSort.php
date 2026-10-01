<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Database\Eloquent\Builder;

/**
 * Strict, allowlisted catalog sort orderings.
 *
 * Every ordering is explicitly mapped here; arbitrary `orderBy` input is never
 * accepted. Every ordering appends an `id` tie-break so pagination stays
 * deterministic. DEFAULT is the same contract as NEWEST: `created_at DESC, id ASC`,
 * which is what the user-facing "Terbaru" label promises.
 */
enum CatalogSort: string
{
    case DEFAULT = 'default';
    case PRICE_ASC = 'price_asc';
    case PRICE_DESC = 'price_desc';
    case TKDN_DESC = 'tkdn_desc';
    case NEWEST = 'newest';

    /**
     * Resolve a requested sort key; unknown values fall back to DEFAULT.
     */
    public static function fromRequest(?string $value): self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        return self::DEFAULT;
    }

    /**
     * Apply the ordering to a Product query and return it for chaining.
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::DEFAULT, self::NEWEST => $query->orderByDesc('created_at')->orderBy('id'),
            self::PRICE_ASC => $query->orderBy('base_price')->orderBy('id'),
            self::PRICE_DESC => $query->orderByDesc('base_price')->orderBy('id'),
            self::TKDN_DESC => $query->orderByRaw('(tkdn_percentage IS NULL), tkdn_percentage DESC')->orderBy('id'),
        };
    }
}
