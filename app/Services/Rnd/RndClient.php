<?php

namespace App\Services\Rnd;

use App\DataTransferObjects\RndResult;
use App\Enums\RndStatus;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class RndClient
{
    public function checkNumber(string $phone, string $consentDate): RndResult
    {
        $companyId = config('services.rnd.company_id');

        if (! $companyId) {
            return new RndResult(
                status: RndStatus::Error,
                error: 'RND company ID is not configured.',
            );
        }

        try {
            $idToken = $this->getIdToken();

            $response = $this->http($idToken)->post('/api/tn', [
                'tnList' => [
                    [
                        'tn' => $phone,
                        'date' => $consentDate,
                        'companyId' => $companyId,
                    ],
                ],
            ]);

            if (! $response->successful()) {
                return new RndResult(
                    status: RndStatus::Error,
                    error: 'HTTP '.$response->status().': '.$response->body(),
                );
            }

            $disconnected = strtolower((string) data_get($response->json(), 'replies.0.disconnected'));

            return new RndResult(status: $this->mapDisconnected($disconnected));
        } catch (Throwable $exception) {
            return new RndResult(
                status: RndStatus::Error,
                error: $exception->getMessage(),
            );
        }
    }

    private function mapDisconnected(string $disconnected): RndStatus
    {
        return match ($disconnected) {
            'yes' => RndStatus::Reassigned,
            'no' => RndStatus::Clear,
            'no_data' => RndStatus::NoData,
            default => RndStatus::Error,
        };
    }

    private function getIdToken(): string
    {
        $refreshToken = config('services.rnd.refresh_token');

        if (! $refreshToken) {
            throw new \RuntimeException('RND refresh token is not configured.');
        }

        $baseUrl = $this->baseUrl();
        $cacheKey = 'rnd_id_token:'.md5($baseUrl.':'.$refreshToken);

        return Cache::remember($cacheKey, 3300, function () use ($baseUrl, $refreshToken): string {
            $response = Http::baseUrl($baseUrl)
                ->timeout(15)
                ->acceptJson()
                ->asJson()
                ->post('/b/public/api/idToken', [
                    'refreshToken' => $refreshToken,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('RND idToken request failed: '.$response->body());
            }

            $idToken = $response->json('idToken');

            if (! is_string($idToken) || $idToken === '') {
                throw new \RuntimeException('RND idToken response missing idToken.');
            }

            return $idToken;
        });
    }

    private function http(string $idToken): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(15)
            ->withHeaders(['Authorization' => $idToken])
            ->acceptJson()
            ->asJson();
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.rnd.base_url', 'https://api.reassigned.us'), '/');
    }
}
