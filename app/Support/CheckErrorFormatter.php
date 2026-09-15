<?php

namespace App\Support;

class CheckErrorFormatter
{
    /**
     * @return array{summary: ?string, code: ?string, message: string, details: ?string}
     */
    public static function parse(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [
                'summary' => null,
                'code' => null,
                'message' => '',
                'details' => null,
            ];
        }

        [$summary, $body] = self::splitPrefix($raw);
        $decoded = self::decodeJson($body);

        if ($decoded !== null) {
            return self::fromJson($summary, $decoded);
        }

        if (self::looksLikeHtml($body)) {
            $body = self::plainTextFromHtml($body);
        }

        return [
            'summary' => $summary,
            'code' => self::httpStatusCode($summary),
            'message' => $body,
            'details' => null,
        ];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private static function splitPrefix(string $raw): array
    {
        if (preg_match('/^(HTTP \d{3}):\s*(.*)$/s', $raw, $matches) === 1) {
            return [$matches[1], trim($matches[2])];
        }

        if (preg_match('/^([^:]{3,80}):\s*([\[{].*)$/s', $raw, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return [null, $raw];
    }

    private static function decodeJson(string $value): mixed
    {
        $trimmed = trim($value);

        if ($trimmed === '' || (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '['))) {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * @param  array<mixed>|string|int|float|bool|null  $decoded
     * @return array{summary: ?string, code: ?string, message: string, details: ?string}
     */
    private static function fromJson(?string $summary, mixed $decoded): array
    {
        $pretty = json_encode(
            $decoded,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if (! is_string($pretty) || $pretty === '') {
            $pretty = is_scalar($decoded) ? (string) $decoded : '{}';
        }

        $code = self::jsonCode($decoded) ?? self::httpStatusCode($summary);
        $message = self::jsonMessage($decoded);

        if ($message === null || $message === '') {
            return [
                'summary' => $summary,
                'code' => $code,
                'message' => $pretty,
                'details' => null,
            ];
        }

        return [
            'summary' => $summary,
            'code' => $code,
            'message' => $message,
            'details' => $pretty,
        ];
    }

    private static function jsonMessage(mixed $decoded): ?string
    {
        $row = self::primaryJsonRow($decoded);

        if ($row === null) {
            return null;
        }

        foreach (['errorMessage', 'error_description', 'message', 'error'] as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return self::normalizeMessage($value);
            }
        }

        return null;
    }

    private static function jsonCode(mixed $decoded): ?string
    {
        $row = self::primaryJsonRow($decoded);

        if ($row === null) {
            return null;
        }

        foreach (['errorCode', 'error_code', 'error'] as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && trim($value) !== '' && ! str_contains($value, ' ')) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function primaryJsonRow(mixed $decoded): ?array
    {
        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        if (array_is_list($decoded)) {
            $first = $decoded[0] ?? null;

            return is_array($first) ? $first : null;
        }

        return $decoded;
    }

    private static function httpStatusCode(?string $summary): ?string
    {
        if ($summary === null || preg_match('/^HTTP (\d{3})$/', $summary, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private static function normalizeMessage(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);

        return trim($message);
    }

    private static function looksLikeHtml(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '<') && str_contains($trimmed, '>');
    }

    private static function plainTextFromHtml(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
