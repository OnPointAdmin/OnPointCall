<?php

namespace App\Services\Salesforce;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SalesforceClient
{
    public function isConfigured(): bool
    {
        $clientId = config('services.qualification.client_id');
        $clientSecret = config('services.qualification.client_secret');

        return filled($clientId) && filled($clientSecret);
    }

    public function getAccessToken(): string
    {
        $clientId = config('services.qualification.client_id');
        $clientSecret = config('services.qualification.client_secret');

        if (! $clientId || ! $clientSecret) {
            throw new \RuntimeException('Salesforce qualification credentials are not configured.');
        }

        $instanceUrl = $this->instanceUrl();
        $cacheKey = 'qualification_sf_token:'.md5($instanceUrl.':'.$clientId);

        return Cache::remember($cacheKey, 3500, function () use ($instanceUrl, $clientId, $clientSecret): string {
            $response = Http::asForm()
                ->timeout(15)
                ->post($instanceUrl.'/services/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Salesforce token request failed: '.$response->body());
            }

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new \RuntimeException('Salesforce token response missing access_token.');
            }

            return $token;
        });
    }

    public function http(?string $token = null): PendingRequest
    {
        $request = Http::baseUrl($this->instanceUrl())
            ->timeout(20)
            ->acceptJson()
            ->asJson();

        $token ??= $this->getAccessToken();

        return $request->withToken($token);
    }

    public function instanceUrl(): string
    {
        return rtrim((string) config('services.qualification.instance_url', 'https://onpointmrg--staging.sandbox.my.salesforce.com'), '/');
    }

    public function apiVersion(): string
    {
        return (string) config('services.qualification.api_version', 'v64.0');
    }
}
