<?php

namespace App\Services\Salesforce;

use App\Enums\LeadHistoryType;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Log;
use Throwable;

class SalesforceDncService
{
    public const REASON_CUSTOMER = 'Customer Requested';

    public const REASON_MANAGEMENT = 'Management Requested';

    public const SOURCE_PHONE = 'Phone';

    public function __construct(
        private readonly SalesforceClient $salesforce,
        private readonly SalesforceDncClient $client,
    ) {}

    public function push(Lead $lead, ?int $actorId = null): void
    {
        if (! $this->salesforce->isConfigured()) {
            Log::warning('Skipping Salesforce DNC insert; credentials are not configured.', [
                'lead_id' => $lead->id,
            ]);

            $this->recordHistory($lead, salesforceError: 'Salesforce credentials are not configured.', actorId: $actorId);

            return;
        }

        $record = $this->buildRecord($lead, $actorId);

        try {
            $result = $this->client->insert($record);
        } catch (Throwable $exception) {
            $this->recordHistory($lead, salesforceError: $exception->getMessage(), actorId: $actorId);

            throw $exception;
        }

        if ($result->success) {
            $this->recordHistory(
                $lead,
                salesforceId: $result->id,
                salesforceError: null,
                actorId: $actorId,
            );

            return;
        }

        $this->recordHistory(
            $lead,
            salesforceId: null,
            salesforceError: $result->error,
            actorId: $actorId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildRecord(Lead $lead, ?int $actorId = null): array
    {
        $record = [
            'DNC_Reason__c' => $this->reasonForActor($actorId),
            'First_Name__c' => mb_substr(trim((string) $lead->first_name), 0, 40),
            'Last_Name__c' => mb_substr(trim((string) $lead->last_name), 0, 80),
            'Request_Source__c' => self::SOURCE_PHONE,
            'Requested_Date__c' => now()->toDateString(),
        ];

        $phone = PhoneNormalizer::normalize($lead->phone);

        if ($phone === null && is_string($lead->phone) && trim($lead->phone) !== '') {
            $digits = preg_replace('/\D/', '', $lead->phone) ?? '';
            $phone = $digits !== '' ? $digits : trim($lead->phone);
        }

        if (is_string($phone) && $phone !== '') {
            $record['Phone__c'] = $phone;
        }

        $email = is_string($lead->email) ? trim($lead->email) : '';

        if ($email !== '') {
            $record['Email__c'] = $email;
        }

        $note = $this->dispositionNote($lead);

        if ($note !== null) {
            $record['Request_Notes__c'] = mb_substr($note, 0, 255);
        }

        return $record;
    }

    private function reasonForActor(?int $actorId): string
    {
        if ($actorId === null) {
            return self::REASON_CUSTOMER;
        }

        $user = User::withoutGlobalScopes()->find($actorId);

        if ($user?->role?->canAccessAdmin()) {
            return self::REASON_MANAGEMENT;
        }

        return self::REASON_CUSTOMER;
    }

    private function dispositionNote(Lead $lead): ?string
    {
        $history = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::Disposition)
            ->orderByDesc('id')
            ->first();

        $note = $history?->payload['note'] ?? null;

        if (! is_string($note)) {
            return null;
        }

        $trimmed = trim($note);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function recordHistory(
        Lead $lead,
        ?string $salesforceId = null,
        ?string $salesforceError = null,
        ?int $actorId = null,
    ): void {
        $payload = [
            'salesforce_id' => is_string($salesforceId) && $salesforceId !== '' ? $salesforceId : null,
            'salesforce_error' => is_string($salesforceError) && $salesforceError !== '' ? $salesforceError : null,
        ];

        if ($payload['salesforce_id'] === null && $payload['salesforce_error'] === null) {
            return;
        }

        LeadHistory::mergeDncPushPayload($lead, $payload, $actorId);
    }
}
