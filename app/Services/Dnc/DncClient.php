<?php

namespace App\Services\Dnc;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DncClient
{
    public const MAX_PHONES_PER_REQUEST = 50;

    /**
     * @param  list<array{phone: string, reference: string}>  $entries
     * @return list<array<string, mixed>>
     */
    public function scrub(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        if (count($entries) > self::MAX_PHONES_PER_REQUEST) {
            throw new RuntimeException('DNC scrub request exceeds '.self::MAX_PHONES_PER_REQUEST.' phone numbers.');
        }

        $phoneList = implode(',', array_map(
            fn (array $entry): string => $entry['phone'].'|'.$entry['reference'],
            $entries,
        ));

        $timeout = min(600, max(20, 4 * count($entries)));

        $response = $this->http($timeout)->asForm()->post('/app/main/rpc/scrub', [
            'phoneList' => $phoneList,
            'version' => '5',
            'output' => 'json',
            'projId' => $this->projectId(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        $json = $response->json();

        if (is_array($json) && $json !== [] && array_is_list($json)) {
            return $json;
        }

        if (is_array($json) && isset($json['Phone'])) {
            return [$json];
        }

        throw new RuntimeException('Unexpected DNC scrub response: '.$response->body());
    }

    /**
     * @param  list<string>  $phones
     */
    public function addToInternalDnc(array $phones): void
    {
        $phones = array_values(array_unique(array_filter($phones)));

        if ($phones === []) {
            return;
        }

        $timeout = min(600, max(20, 4 * count($phones)));

        $response = $this->http($timeout)->asForm()->post('/app/main/rpc/pdnc', [
            'phoneList' => implode(',', $phones),
            'actionType' => 'add',
            'projId' => $this->projectId(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().': '.$response->body());
        }
    }

    public function isConfigured(): bool
    {
        $loginId = config('services.dnc.login_id');

        return is_string($loginId) && $loginId !== '';
    }

    private function http(int $timeout): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('DNC login ID is not configured.');
        }

        return Http::baseUrl($this->baseUrl())
            ->timeout($timeout)
            ->withHeaders(['loginId' => config('services.dnc.login_id')])
            ->acceptJson();
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.dnc.base_url', 'https://www.dncscrub.com'), '/');
    }

    private function projectId(): string
    {
        return (string) config('services.dnc.project_id', 'ONPNT');
    }
}
