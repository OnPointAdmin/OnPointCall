<?php

namespace App\Support;

use DateTime;

class DncReasonFormatter
{
    /**
     * @param  array<string, array<string, mixed>>  $phones
     */
    public static function formatPhones(array $phones): ?string
    {
        if ($phones === []) {
            return null;
        }

        $parts = [];

        foreach ($phones as $field => $phone) {
            if (! is_array($phone)) {
                continue;
            }

            $formatted = self::formatPhoneReason($phone);

            if ($formatted === null || $formatted === '') {
                continue;
            }

            $label = $field === 'phone_2' ? 'Phone 2' : null;
            $parts[] = $label !== null ? "{$label}: {$formatted}" : $formatted;
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    /**
     * @param  array<string, mixed>  $phone
     */
    public static function formatPhoneReason(array $phone): ?string
    {
        $reason = isset($phone['reason']) ? trim((string) $phone['reason']) : '';
        $resultCode = isset($phone['result_code']) ? strtoupper(trim((string) $phone['result_code'])) : '';
        $flags = is_array($phone['flags'] ?? null) ? $phone['flags'] : [];
        $suppress = isset($phone['suppress']) ? (string) $phone['suppress'] : null;

        if ($reason !== '' && stripos($reason, 'Litigator') !== false) {
            return 'Litigator';
        }

        if ($resultCode === 'P' || in_array('idnc', $flags, true) || $suppress === 'idnc') {
            return 'Internal DNC';
        }

        if ($resultCode === 'I' || in_array('invalid', $flags, true) || $suppress === 'invalid') {
            return 'Invalid number';
        }

        $segments = self::formatReasonSegments($reason);

        if ($segments !== []) {
            return implode(' ', $segments);
        }

        return match ($suppress) {
            'litigator' => 'Litigator',
            'national' => 'National DNC',
            'state' => 'State DNC',
            'dnc' => 'DNC',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private static function formatReasonSegments(string $reason): array
    {
        $parts = array_map('trim', explode(';', $reason));
        $nationalPart = $parts[0] ?? '';
        $statePart = $parts[1] ?? '';
        $segments = [];

        if ($nationalPart !== '' && str_starts_with(strtolower($nationalPart), 'national')) {
            $date = self::extractDate($nationalPart);
            $segments[] = $date !== null
                ? 'National DNC since '.self::formatDate($date)
                : 'National DNC';
        }

        if ($statePart !== '') {
            $date = self::extractDate($statePart);

            if ($date !== null) {
                $segments[] = 'State DNC since '.self::formatDate($date);
            } else {
                $stateName = self::extractStateName($statePart);
                $segments[] = $stateName !== null
                    ? "State DNC ({$stateName})"
                    : 'State DNC';
            }
        }

        return $segments;
    }

    private static function extractDate(string $text): ?string
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $text, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function formatDate(string $isoDate): string
    {
        $date = DateTime::createFromFormat('Y-m-d', $isoDate);

        return $date !== false ? $date->format('m-d-Y') : $isoDate;
    }

    private static function extractStateName(string $statePart): ?string
    {
        if (preg_match('/State\s*\(([^)]+)\)/i', $statePart, $matches) === 1) {
            return trim($matches[1]);
        }

        $withoutDate = preg_replace('/\d{4}-\d{2}-\d{2}/', '', $statePart);
        $name = trim((string) $withoutDate);

        return $name !== '' ? $name : null;
    }
}
