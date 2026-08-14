<?php

namespace App\Services\Qualification;

use App\DataTransferObjects\QualificationResult;
use App\Enums\QualificationStatus;
use App\Models\Company;
use App\Models\Lead;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class QualificationClient
{
    public function qualifyLead(Lead $lead): QualificationResult
    {
        $request = $this->buildRequest($lead);

        if (($request['surveyCompanyId'] ?? null) === null) {
            return new QualificationResult(
                status: QualificationStatus::Error,
                request: $request,
                error: 'Company Salesforce ID is not set (surveyCompanyId).',
            );
        }

        try {
            $token = $this->getAccessToken();

            $response = $this->http($token)->post('/services/apexrest/CustomerQualification', $request);

            if (! $response->successful()) {
                return new QualificationResult(
                    status: QualificationStatus::Error,
                    request: $request,
                    error: 'HTTP '.$response->status().': '.$response->body(),
                );
            }

            $json = $response->json();

            if (! is_array($json)) {
                return new QualificationResult(
                    status: QualificationStatus::Error,
                    request: $request,
                    error: 'Qualification response was not JSON.',
                );
            }

            $errorMessage = $json['errorMessage'] ?? null;

            if (is_string($errorMessage) && trim($errorMessage) !== '') {
                return new QualificationResult(
                    status: QualificationStatus::Error,
                    payload: $json,
                    request: $request,
                    error: trim($errorMessage),
                );
            }

            $qualified = $this->hasQualifiedCompanies($json);

            return new QualificationResult(
                status: $qualified ? QualificationStatus::Qualified : QualificationStatus::NotQualified,
                payload: $json,
                request: $request,
            );
        } catch (Throwable $exception) {
            return new QualificationResult(
                status: QualificationStatus::Error,
                request: $request,
                error: $exception->getMessage(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequest(Lead $lead): array
    {
        $request = [
            'surveyCompanyId' => $this->surveyCompanyId($lead),
            'customerData' => $this->buildCustomerData($lead),
        ];

        $venueId = $this->venueId($lead);

        if ($venueId !== null) {
            $request['venueId'] = $venueId;
        }

        return $request;
    }

    private function surveyCompanyId(Lead $lead): ?string
    {
        $company = Company::query()->find($lead->company_id);
        $id = $company?->salesforce_id;

        if (! is_string($id) || trim($id) === '') {
            return null;
        }

        return trim($id);
    }

    private function venueId(Lead $lead): ?string
    {
        $extra = is_array($lead->extra_fields) ? $lead->extra_fields : [];

        foreach (['venueId', 'venue_id', 'VenueId'] as $key) {
            $value = $extra[$key] ?? null;

            if (is_string($value) && $this->looksLikeSalesforceId($value)) {
                return trim($value);
            }
        }

        if (is_string($lead->venue) && $this->looksLikeSalesforceId($lead->venue)) {
            return trim($lead->venue);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerData(Lead $lead): array
    {
        $extra = is_array($lead->extra_fields) ? $lead->extra_fields : [];
        $zip = preg_replace('/\D/', '', (string) $lead->zip) ?? '';
        $zip = substr($zip, 0, 5);

        $data = [
            'lastName' => (string) ($lead->last_name ?? ''),
            'gender' => (string) ($lead->gender ?? ''),
            'age' => (string) ($lead->age_range ?? ''),
            'marital' => (string) ($lead->marital_status ?? ''),
            'income' => (string) ($lead->annual_income ?? ''),
            'zipCode' => $zip,
            'homeOwner' => (string) ($lead->home_owner ?? ''),
            'qualificationCode' => (string) ($lead->soft_score_code ?? ''),
            'country' => $this->stringFromExtra($extra, ['country']) ?: 'United States',
            'card' => $this->stringFromExtra($extra, ['card', 'credit', 'credit_range']),
            'employment' => $this->stringFromExtra($extra, ['employment', 'employment_status']),
            'stayType' => $this->stringFromExtra($extra, ['stayType', 'stay_type']),
            'scheduled' => $this->stringFromExtra($extra, ['scheduled']),
        ];

        foreach ($extra as $key => $value) {
            if (! is_string($key) || array_key_exists($key, $data)) {
                continue;
            }

            if (in_array($key, ['venueId', 'venue_id', 'VenueId'], true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $data[$key] = is_scalar($value) ? (string) $value : $value;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  list<string>  $keys
     */
    private function stringFromExtra(array $extra, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $extra[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function hasQualifiedCompanies(array $json): bool
    {
        foreach (['qualifiedCompaniesLead', 'qualifiedCompaniesBooking'] as $key) {
            $list = $json[$key] ?? [];

            if (is_array($list) && $list !== []) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeSalesforceId(string $value): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9]{15}([a-zA-Z0-9]{3})?$/', trim($value));
    }

    private function getAccessToken(): string
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

    private function http(string $token): PendingRequest
    {
        return Http::baseUrl($this->instanceUrl())
            ->timeout(20)
            ->withToken($token)
            ->acceptJson()
            ->asJson();
    }

    private function instanceUrl(): string
    {
        return rtrim((string) config('services.qualification.instance_url', 'https://onpointmrg--staging.sandbox.my.salesforce.com'), '/');
    }
}
