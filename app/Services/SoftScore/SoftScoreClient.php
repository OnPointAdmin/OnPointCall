<?php

namespace App\Services\SoftScore;

use App\DataTransferObjects\SoftScoreResult;
use App\Enums\SoftScoreStatus;
use App\Models\AppSetting;
use App\Models\Lead;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SoftScoreClient
{
    public function scoreLead(Lead $lead, ?AppSetting $settings = null): SoftScoreResult
    {
        $settings ??= AppSetting::withoutGlobalScopes()
            ->where('company_id', $lead->company_id)
            ->first();

        $baseUrl = rtrim($settings?->soft_score_base_url ?: config('services.soft_score.base_url'), '/');
        $originator = $settings?->soft_score_originator ?: 'KALEO';

        try {
            $token = $this->getAccessToken($baseUrl);

            $response = $this->http($baseUrl, $token)
                ->withHeaders(['X-ORIGINATOR-APPLICATION' => $originator])
                ->post('/marketing/v1/leads/softscore', [
                    'leadRequest' => $this->buildPayload($lead),
                ]);

            if (! $response->successful()) {
                return new SoftScoreResult(
                    status: SoftScoreStatus::Error,
                    error: 'HTTP '.$response->status().': '.$response->body(),
                );
            }

            $code = data_get($response->json(), 'lead.creditScore.0.creditBand.qualificationCode');
            $qualificationCode = is_string($code) && trim($code) !== '' ? trim($code) : null;

            return new SoftScoreResult(
                status: SoftScoreStatus::Complete,
                qualificationCode: $qualificationCode,
            );
        } catch (Throwable $exception) {
            return new SoftScoreResult(
                status: SoftScoreStatus::Error,
                error: $exception->getMessage(),
            );
        }
    }

    private function getAccessToken(string $baseUrl): string
    {
        $clientId = config('services.soft_score.client_id');
        $clientSecret = config('services.soft_score.client_secret');

        if (! $clientId || ! $clientSecret) {
            throw new \RuntimeException('Soft Score API credentials are not configured.');
        }

        $cacheKey = 'soft_score_token:'.md5($baseUrl.':'.$clientId);

        return Cache::remember($cacheKey, 3500, function () use ($baseUrl, $clientId, $clientSecret): string {
            $response = Http::asForm()
                ->timeout(15)
                ->post("{$baseUrl}/oauth/v2/accesstoken?grant_type=client_credentials", [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Soft Score token request failed: '.$response->body());
            }

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new \RuntimeException('Soft Score token response missing access_token.');
            }

            return $token;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Lead $lead): array
    {
        $zip = preg_replace('/\D/', '', (string) $lead->zip) ?? '';
        $zip = substr($zip, 0, 5);
        $state = strtoupper((string) $lead->state);

        $address = [
            'addressLine1' => (string) $lead->address,
            'city' => (string) $lead->city,
            'state' => $state,
            'country' => 'USA',
            'postalCode' => $zip,
        ];

        return [
            'firstName' => (string) $lead->first_name,
            'lastName' => (string) $lead->last_name,
            'homePhone' => PhoneNormalizer::normalize($lead->phone),
            'primaryEmail' => (string) $lead->email,
            'addressLine1' => (string) $lead->address,
            'city' => (string) $lead->city,
            'state' => $state,
            'postalCode' => $zip,
            'address' => $address,
            'ownerFlag' => 'N',
            'creditScore' => [
                [
                    'softScoreLetterPrintInd' => 'N',
                    'softScoreLetterSendInd' => 'N',
                ],
            ],
        ];
    }

    private function http(string $baseUrl, string $token): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->timeout(15)
            ->withToken($token)
            ->acceptJson()
            ->asJson();
    }
}
