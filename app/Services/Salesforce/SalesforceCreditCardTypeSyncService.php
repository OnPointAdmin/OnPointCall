<?php

namespace App\Services\Salesforce;

use App\Models\Lead;
use Illuminate\Support\Collection;
use RuntimeException;

class SalesforceCreditCardTypeSyncService
{
    public const CHUNK_SIZE = 50;

    public function __construct(
        private readonly SalesforceClient $salesforce,
    ) {}

    public function isConfigured(): bool
    {
        return $this->salesforce->isConfigured();
    }

    /**
     * @return array{
     *     scanned: int,
     *     skipped_not_lead_id: int,
     *     queried: int,
     *     updated: int,
     *     unchanged: int,
     *     not_found: int
     * }
     */
    public function sync(?int $companyId = null, bool $dryRun = false, bool $force = false): array
    {
        $stats = [
            'scanned' => 0,
            'skipped_not_lead_id' => 0,
            'queried' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'not_found' => 0,
        ];

        $query = Lead::withoutGlobalScopes()
            ->whereNotNull('external_lead_id')
            ->where('external_lead_id', '!=', '');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if (! $force) {
            $query->where(function ($blank): void {
                $blank->whereNull('credit_card_type')->orWhere('credit_card_type', '');
            });
        }

        $query->orderBy('id')->chunkById(self::CHUNK_SIZE, function (Collection $leads) use (&$stats, $dryRun): void {
            $this->syncChunk($leads, $stats, $dryRun);
        });

        return $stats;
    }

    public function isSalesforceLeadId(string $value): bool
    {
        return preg_match('/^00Q[a-zA-Z0-9]{12}([a-zA-Z0-9]{3})?$/', trim($value)) === 1;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @param  array{
     *     scanned: int,
     *     skipped_not_lead_id: int,
     *     queried: int,
     *     updated: int,
     *     unchanged: int,
     *     not_found: int
     * }  $stats
     */
    private function syncChunk(Collection $leads, array &$stats, bool $dryRun): void
    {
        $stats['scanned'] += $leads->count();

        $candidates = [];

        foreach ($leads as $lead) {
            $externalId = trim((string) $lead->external_lead_id);

            if (! $this->isSalesforceLeadId($externalId)) {
                $stats['skipped_not_lead_id']++;

                continue;
            }

            $candidates[] = $lead;
        }

        if ($candidates === []) {
            return;
        }

        $ids = [];

        foreach ($candidates as $lead) {
            $ids[] = trim((string) $lead->external_lead_id);
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        $stats['queried'] += count($ids);

        $records = $this->queryLeads($ids);
        $recordsById = $this->indexRecords($records);

        foreach ($candidates as $lead) {
            $record = $this->recordForLead($recordsById, trim((string) $lead->external_lead_id));

            if ($record === null) {
                $stats['not_found']++;

                continue;
            }

            $sfValue = trim((string) ($record[$this->creditCardTypeField()] ?? ''));
            $current = trim((string) ($lead->credit_card_type ?? ''));

            if ($sfValue === '' || $sfValue === $current) {
                $stats['unchanged']++;

                continue;
            }

            $stats['updated']++;

            if (! $dryRun) {
                $lead->update(['credit_card_type' => $sfValue]);
            }
        }
    }

    /**
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    private function queryLeads(array $ids): array
    {
        $object = (string) config('services.salesforce.leads.object', 'Lead');
        $idField = $this->idField();
        $cardField = $this->creditCardTypeField();
        $quoted = implode(', ', array_map(
            static fn (string $id): string => "'".str_replace("'", "\\'", $id)."'",
            $ids,
        ));

        $soql = 'SELECT '.$idField.', '.$cardField.' FROM '.$object.' WHERE '.$idField.' IN ('.$quoted.')';

        try {
            return $this->salesforce->query($soql);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'INVALID_TYPE')
                || str_contains($exception->getMessage(), 'sObject type')) {
                throw new RuntimeException(
                    'The Salesforce integration user cannot query Lead. Grant Read on Lead and field-level security on Type_of_Credit_Card__c, then re-run salesforce:sync-credit-card-type.',
                    0,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, array<string, mixed>>
     */
    private function indexRecords(array $records): array
    {
        $index = [];
        $idField = $this->idField();

        foreach ($records as $record) {
            $id = trim((string) ($record[$idField] ?? ''));

            if ($id === '') {
                continue;
            }

            $index[$id] = $record;
            $index[substr($id, 0, 15)] = $record;
        }

        return $index;
    }

    /**
     * @param  array<string, array<string, mixed>>  $recordsById
     * @return array<string, mixed>|null
     */
    private function recordForLead(array $recordsById, string $externalId): ?array
    {
        return $recordsById[$externalId] ?? $recordsById[substr($externalId, 0, 15)] ?? null;
    }

    private function idField(): string
    {
        return (string) config('services.salesforce.leads.fields.id', 'Id');
    }

    private function creditCardTypeField(): string
    {
        return (string) config('services.salesforce.leads.fields.credit_card_type', 'Type_of_Credit_Card__c');
    }
}
