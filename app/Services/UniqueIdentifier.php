<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates prefixed random business identifiers (e.g. "ORD-XXXXXXXXXX").
 * Randomness is checked against the target unique column, retrying with a
 * fresh token if a collision is ever detected, so a rare birthday collision
 * cannot 500 a request.
 */
class UniqueIdentifier
{
    public const TOKEN_LENGTH = 10;

    public const MAX_ATTEMPTS = 10;

    /**
     * @param  class-string<Model>  $model
     */
    public static function generate(string $prefix, string $model, string $column): string
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $identifier = $prefix.'-'.strtoupper(Str::random(self::TOKEN_LENGTH));

            if (! $model::query()->where($column, $identifier)->exists()) {
                return $identifier;
            }
        }

        throw new \RuntimeException("Unable to allocate a unique {$prefix} identifier after ".self::MAX_ATTEMPTS.' attempts.');
    }
}
