<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Bounds on the feed figures a farmer can enter.
 *
 * Sized to sit inside the decimal(14,2) columns these end up in, with room for
 * a whole farm's worth to be summed without overflowing.
 */
final class FeedLimits
{
    public const MAX_FEED_USED_BEFORE = 999999999.99;

    public const MAX_FEED_QUANTITY = 999999999.99;

    public const MAX_STORE = 999999999.99;

    /** Validation rules for a "feed already used" field. */
    public static function feedUsedBeforeRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'numeric',
            'min:0',
            'max:' . self::MAX_FEED_USED_BEFORE,
        ];
    }

    /** Validation rules for a recorded feed quantity. */
    public static function feedQuantityRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'numeric',
            'min:0',
            'max:' . self::MAX_FEED_QUANTITY,
        ];
    }

    /** Validation rules for a feed store figure. */
    public static function storeRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'numeric',
            'min:0',
            'max:' . self::MAX_STORE,
        ];
    }

    /**
     * Throw a 422 when a value decoded from JSON is out of range.
     *
     * Used where the figure arrives inside a JSON blob rather than as a form
     * field, so the validator cannot reach it.
     *
     * @throws ValidationException
     */
    public static function assertFeedUsedBefore(float $value, string $field, string $label): void
    {
        if ($value >= 0 && $value <= self::MAX_FEED_USED_BEFORE) {
            return;
        }

        throw ValidationException::withMessages([
            $field => sprintf(
                'Feed already used for %s must be between 0 and %s kg.',
                $label,
                number_format(floor(self::MAX_FEED_USED_BEFORE), 0)
            ),
        ]);
    }
}
