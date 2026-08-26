<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * JSON object stored as jsonb. Tolerates a plain JSON string or a
 * double-encoded JSON string (e.g. Filament TextInput saving an array).
 *
 * @implements CastsAttributes<array<string, string>, array<string, string>|string|null>
 */
class AssociativeJsonMap implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return self::normalize($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode(self::normalize($value), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    public static function normalize(mixed $value): array
    {
        if (is_array($value)) {
            return self::stringify($value);
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? self::stringify($decoded) : [];
    }

    /**
     * @param  array<mixed, mixed>  $map
     * @return array<string, string>
     */
    private static function stringify(array $map): array
    {
        $normalized = [];

        foreach ($map as $key => $item) {
            if (! is_scalar($item) && $item !== null) {
                continue;
            }

            $normalized[(string) $key] = (string) $item;
        }

        return $normalized;
    }
}
