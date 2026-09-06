<?php

namespace App\Support;

class NpaToState
{
    /**
     * @var array<string, string>|null
     */
    private static ?array $map = null;

    public static function stateForPhone(?string $phone): ?string
    {
        $npa = self::npaFromPhone($phone);

        if ($npa === null) {
            return null;
        }

        return self::stateForNpa($npa);
    }

    public static function stateForNpa(string $npa): ?string
    {
        $npa = self::normalizeNpa($npa);

        if ($npa === null) {
            return null;
        }

        return self::map()[$npa] ?? null;
    }

    public static function phoneMatchesState(string $stateCode, ?string $phone): bool
    {
        $stateCode = strtoupper(trim($stateCode));
        $mapped = self::stateForPhone($phone);

        return $mapped !== null && $mapped === $stateCode;
    }

    public static function npaFromPhone(?string $phone): ?string
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if ($normalized === null || strlen($normalized) !== 10) {
            return null;
        }

        return substr($normalized, 0, 3);
    }

    private static function normalizeNpa(string $npa): ?string
    {
        $digits = preg_replace('/\D/', '', $npa) ?? '';

        if (strlen($digits) !== 3) {
            return null;
        }

        return $digits;
    }

    /**
     * @return array<string, string>
     */
    private static function map(): array
    {
        return self::$map ??= require __DIR__.'/data/npa_to_state.php';
    }
}
