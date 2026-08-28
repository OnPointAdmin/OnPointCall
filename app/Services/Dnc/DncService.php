<?php

namespace App\Services\Dnc;

use App\DataTransferObjects\DncPhoneResult;
use App\DataTransferObjects\DncResult;
use App\Enums\DncStatus;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DncService
{
    public function __construct(
        private readonly DncClient $client,
    ) {}

    /**
     * @param  Collection<int, Lead>  $leads
     */
    public function checkLeads(Collection $leads, ?int $actorId = null): void
    {
        if ($leads->isEmpty()) {
            return;
        }

        foreach ($leads as $lead) {
            $this->markPending($lead);
        }

        $entries = [];
        $leadsById = $leads->keyBy('id');
        $resultsByLeadId = [];
        $ignoreNationalDncByLeadId = $this->ignoreNationalDncByLeadId($leads);

        foreach ($leads as $lead) {
            $phones = $this->leadPhones($lead);

            if ($phones === []) {
                $resultsByLeadId[$lead->id] = new DncResult(
                    status: DncStatus::Error,
                    error: 'Lead has no valid phone number.',
                );

                continue;
            }

            foreach ($phones as $field => $phone) {
                $entries[] = [
                    'lead_id' => $lead->id,
                    'field' => $field,
                    'phone' => $phone,
                    'reference' => $lead->id.':'.$field,
                ];
            }
        }

        foreach (array_chunk($entries, DncClient::MAX_PHONES_PER_REQUEST) as $chunk) {
            try {
                $responses = $this->client->scrub(array_map(
                    fn (array $entry): array => [
                        'phone' => $entry['phone'],
                        'reference' => $entry['reference'],
                    ],
                    $chunk,
                ));

                $byReference = $this->indexResponses($responses);

                foreach ($chunk as $entry) {
                    $raw = $byReference[$entry['reference']]
                        ?? $this->matchResponseByPhone($responses, $entry['phone']);

                    if ($raw === null) {
                        $resultsByLeadId[$entry['lead_id']] = new DncResult(
                            status: DncStatus::Error,
                            error: 'DNC scrub response missing phone '.$entry['phone'].'.',
                        );

                        continue;
                    }

                    if (($resultsByLeadId[$entry['lead_id']] ?? null)?->status === DncStatus::Error) {
                        continue;
                    }

                    $phoneResult = $this->mapPhoneResult($entry['field'], $entry['phone'], $raw);
                    $existing = $resultsByLeadId[$entry['lead_id']] ?? new DncResult(
                        status: DncStatus::Clear,
                        phones: [],
                    );

                    $phones = $existing->phones;
                    $phones[$entry['field']] = $phoneResult;
                    $resultsByLeadId[$entry['lead_id']] = $this->combinePhoneResults(
                        $phones,
                        $ignoreNationalDncByLeadId[$entry['lead_id']] ?? false,
                    );
                }
            } catch (Throwable $exception) {
                $chunkLeadIds = array_unique(array_column($chunk, 'lead_id'));

                foreach ($chunkLeadIds as $leadId) {
                    $resultsByLeadId[$leadId] = new DncResult(
                        status: DncStatus::Error,
                        error: $exception->getMessage(),
                    );
                }
            }
        }

        foreach ($leadsById as $lead) {
            $result = $resultsByLeadId[$lead->id] ?? new DncResult(
                status: DncStatus::Error,
                error: 'DNC scrub did not return a result for this lead.',
            );

            $this->persistResult($lead, $result, $actorId);
        }
    }

    public function pushInternalDnc(Lead $lead): void
    {
        if (! $this->client->isConfigured()) {
            Log::warning('Skipping DNC.com IDNC push; login ID is not configured.', [
                'lead_id' => $lead->id,
            ]);

            return;
        }

        $phones = array_values($this->leadPhones($lead));

        if ($phones === []) {
            Log::warning('Skipping DNC.com IDNC push; lead has no valid phone.', [
                'lead_id' => $lead->id,
            ]);

            return;
        }

        $this->client->addToInternalDnc($phones);

        LeadHistory::mergeDncPushPayload($lead, [
            'phones' => $phones,
            'project_id' => config('services.dnc.project_id'),
        ]);
    }

    /**
     * @param  array<string, DncPhoneResult>  $phones
     */
    public function combinePhoneResults(array $phones, bool $ignoreNationalDnc = false): DncResult
    {
        $blockingReasons = ['litigator', 'state', 'idnc', 'dnc'];

        if (! $ignoreNationalDnc) {
            $blockingReasons[] = 'national';
        }

        $hitReason = null;
        $hasInvalid = false;
        $ignoredReasons = [];

        foreach ($blockingReasons as $reason) {
            foreach ($phones as $phone) {
                if ($this->phoneHasFlag($phone, $reason)) {
                    $hitReason = $reason;
                    break 2;
                }
            }
        }

        foreach ($phones as $phone) {
            if ($this->phoneHasFlag($phone, 'invalid')) {
                $hasInvalid = true;
            }

            if ($ignoreNationalDnc && $this->phoneHasFlag($phone, 'national')) {
                $ignoredReasons[] = 'national';
            }
        }

        $ignoredReasons = array_values(array_unique($ignoredReasons));

        if ($hitReason !== null) {
            return new DncResult(
                status: DncStatus::Hit,
                phones: $phones,
                hitReason: $hitReason,
                ignoreNationalDnc: $ignoreNationalDnc,
                ignoredReasons: $ignoredReasons,
            );
        }

        if ($hasInvalid) {
            return new DncResult(
                status: DncStatus::Invalid,
                phones: $phones,
                hitReason: 'invalid',
                ignoreNationalDnc: $ignoreNationalDnc,
                ignoredReasons: $ignoredReasons,
            );
        }

        return new DncResult(
            status: DncStatus::Clear,
            phones: $phones,
            ignoreNationalDnc: $ignoreNationalDnc,
            ignoredReasons: $ignoredReasons,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function mapPhoneResult(string $field, string $phone, array $raw): DncPhoneResult
    {
        $resultCode = isset($raw['ResultCode']) ? (string) $raw['ResultCode'] : null;
        $reason = isset($raw['Reason']) ? (string) $raw['Reason'] : null;
        $flags = $this->classifyFlags($resultCode, $reason);

        return new DncPhoneResult(
            field: $field,
            phone: $phone,
            resultCode: $resultCode,
            reason: $reason,
            suppress: $this->primaryFlag($flags),
            flags: $flags,
            raw: $raw,
        );
    }

    public function classify(?string $resultCode, ?string $reason): ?string
    {
        return $this->primaryFlag($this->classifyFlags($resultCode, $reason));
    }

    /**
     * @return list<string>
     */
    public function classifyFlags(?string $resultCode, ?string $reason): array
    {
        $code = strtoupper(trim((string) $resultCode));
        $reasonText = trim((string) $reason);
        $flags = [];

        if ($code === 'P') {
            $flags[] = 'idnc';
        }

        if ($code === 'I') {
            $flags[] = 'invalid';
        }

        if (stripos($reasonText, 'Litigator') !== false) {
            $flags[] = 'litigator';
        }

        $parts = array_map('trim', explode(';', $reasonText));
        $nationalPart = $parts[0] ?? '';
        $statePart = $parts[1] ?? '';

        if ($nationalPart !== '' && str_starts_with(strtolower($nationalPart), 'national')) {
            $flags[] = 'national';
        }

        if ($statePart !== '') {
            $flags[] = 'state';
        }

        if ($code === 'D' && $flags === []) {
            $flags[] = $nationalPart !== '' ? 'state' : 'dnc';
        }

        return array_values(array_unique($flags));
    }

    /**
     * @param  list<string>  $flags
     */
    public function primaryFlag(array $flags): ?string
    {
        foreach (['litigator', 'state', 'idnc', 'dnc', 'national', 'invalid'] as $reason) {
            if (in_array($reason, $flags, true)) {
                return $reason;
            }
        }

        return null;
    }

    private function markPending(Lead $lead): void
    {
        $previousStatus = $lead->dnc_status;
        $batchId = $lead->import_batch_id;

        DB::transaction(function () use ($lead, $previousStatus, $batchId): void {
            if ($batchId) {
                $this->moveCompletedCounterToPending($batchId, $previousStatus);
            }

            $lead->update([
                'dnc_status' => DncStatus::Pending,
                'dnc_last_error' => null,
            ]);
        });
    }

    private function persistResult(Lead $lead, DncResult $result, ?int $actorId): void
    {
        DB::transaction(function () use ($lead, $result, $actorId): void {
            $previousLeadStatus = $lead->status;

            $updates = [
                'dnc_status' => $result->status,
                'dnc_checked_at' => now(),
                'dnc_last_error' => $result->error,
                'dnc_result' => $result->error ? $lead->dnc_result : $result->toArray(),
            ];

            $newLeadStatus = $this->leadStatusFor($result->status, $previousLeadStatus);

            if ($newLeadStatus !== null) {
                $updates['status'] = $newLeadStatus;
            }

            $lead->update($updates);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $actorId,
                'event_type' => LeadHistoryType::DncCheck,
                'occurred_at' => now(),
                'payload' => [
                    'status' => $result->status->value,
                    'hit_reason' => $result->hitReason,
                    'ignore_national_dnc' => $result->ignoreNationalDnc,
                    'ignored_reasons' => $result->ignoredReasons,
                    'error' => $result->error,
                    'phones' => $result->toArray()['phones'],
                ],
            ]);

            if ($newLeadStatus !== null && $previousLeadStatus !== $newLeadStatus) {
                LeadHistory::withoutGlobalScopes()->create([
                    'company_id' => $lead->company_id,
                    'lead_id' => $lead->id,
                    'actor_id' => $actorId,
                    'event_type' => LeadHistoryType::StatusChange,
                    'occurred_at' => now(),
                    'payload' => [
                        'from' => $previousLeadStatus->value,
                        'to' => $newLeadStatus->value,
                        'reason' => $result->hitReason !== null
                            ? 'dnc_'.$result->hitReason
                            : 'dnc_'.$result->status->value,
                    ],
                ]);
            }

            if ($lead->import_batch_id) {
                $this->completeBatchCounter($lead->import_batch_id, $result->status);
            }
        });
    }

    private function leadStatusFor(DncStatus $status, LeadStatus $current): ?LeadStatus
    {
        return match ($status) {
            DncStatus::Hit => LeadStatus::Dnc,
            DncStatus::Invalid => in_array($current, [LeadStatus::Booked, LeadStatus::Dnc], true)
                ? null
                : LeadStatus::Terminal,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function leadPhones(Lead $lead): array
    {
        $phones = [];
        $primary = PhoneNormalizer::normalize($lead->phone);

        if ($primary !== null) {
            $phones['phone'] = $primary;
        }

        $secondary = PhoneNormalizer::normalize($lead->phone_2);

        if ($secondary !== null && $secondary !== $primary) {
            $phones['phone_2'] = $secondary;
        }

        return $phones;
    }

    /**
     * @param  list<array<string, mixed>>  $responses
     * @return array<string, array<string, mixed>>
     */
    private function indexResponses(array $responses): array
    {
        $indexed = [];

        foreach ($responses as $response) {
            $reference = isset($response['Reserved']) ? (string) $response['Reserved'] : '';

            if ($reference !== '') {
                $indexed[$reference] = $response;
            }
        }

        return $indexed;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array<int, bool>
     */
    private function ignoreNationalDncByLeadId(Collection $leads): array
    {
        $batchIds = $leads->pluck('import_batch_id')->filter()->unique()->all();
        $ignoreByBatchId = [];

        if ($batchIds !== []) {
            $ignoreByBatchId = ImportBatch::withoutGlobalScopes()
                ->whereIn('id', $batchIds)
                ->get()
                ->mapWithKeys(fn (ImportBatch $batch): array => [$batch->id => (bool) $batch->ignore_national_dnc])
                ->all();
        }

        $ignoreByLeadId = [];

        foreach ($leads as $lead) {
            $batchId = $lead->import_batch_id;
            $ignoreByLeadId[$lead->id] = $batchId !== null
                && (bool) ($ignoreByBatchId[$batchId] ?? false);
        }

        return $ignoreByLeadId;
    }

    private function phoneHasFlag(DncPhoneResult $phone, string $reason): bool
    {
        return $phone->suppress === $reason || in_array($reason, $phone->flags, true);
    }

    /**
     * @param  list<array<string, mixed>>  $responses
     * @return array<string, mixed>|null
     */
    private function matchResponseByPhone(array $responses, string $phone): ?array
    {
        foreach ($responses as $response) {
            if ((string) ($response['Phone'] ?? '') === $phone) {
                return $response;
            }
        }

        return null;
    }

    private function moveCompletedCounterToPending(int $batchId, ?DncStatus $previous): void
    {
        if ($previous === null || $previous === DncStatus::Pending) {
            return;
        }

        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'dnc_pending' => $batch->dnc_pending + 1,
        ];

        match ($previous) {
            DncStatus::Clear => $updates['dnc_clear'] = max(0, $batch->dnc_clear - 1),
            DncStatus::Hit => $updates['dnc_hit'] = max(0, $batch->dnc_hit - 1),
            DncStatus::Invalid => $updates['dnc_invalid'] = max(0, $batch->dnc_invalid - 1),
            DncStatus::Error => $updates['dnc_error'] = max(0, $batch->dnc_error - 1),
            DncStatus::Pending => null,
        };

        $batch->update($updates);
    }

    private function completeBatchCounter(int $batchId, DncStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'dnc_pending' => max(0, $batch->dnc_pending - 1),
        ];

        match ($status) {
            DncStatus::Clear => $updates['dnc_clear'] = $batch->dnc_clear + 1,
            DncStatus::Hit => $updates['dnc_hit'] = $batch->dnc_hit + 1,
            DncStatus::Invalid => $updates['dnc_invalid'] = $batch->dnc_invalid + 1,
            DncStatus::Error => $updates['dnc_error'] = $batch->dnc_error + 1,
            DncStatus::Pending => null,
        };

        $batch->update($updates);
    }
}
