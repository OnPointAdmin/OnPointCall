<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\LeadStatus;
use App\Jobs\SoftScoreLeadJob;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Support\PhoneNormalizer;
use App\Support\TimezoneResolver;
use Carbon\Carbon;
use RuntimeException;
use SplFileObject;

class LeadImportService
{
    /**
     * @var array<string, string>
     */
    public const KNOWN_IMPORT_FIELDS = [
        'phone' => 'Phone',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'address' => 'Address',
        'city' => 'City',
        'state' => 'State',
        'zip' => 'Zip',
        'email' => 'Email',
        'venue' => 'Venue',
        'event' => 'Event',
        'external_lead_id' => 'External lead ID',
        'consent_token' => 'Consent token',
        'partner_list' => 'Partner list',
        'file_name' => 'File name',
        'age_range' => 'Age range',
        'annual_income' => 'Annual income',
        'marital_status' => 'Marital status',
        'gender' => 'Gender',
        'home_owner' => 'Homeowner',
        'original_lead_submit_date' => 'Original submit date',
        'booking_id' => 'Booking ID',
        'phone_2' => 'Phone 2',
        'address_2' => 'Address 2',
        'tour_location' => 'Tour location',
        'tour_date' => 'Tour date',
        'premiums' => 'Premiums',
        'tour_result' => 'Tour result',
        'tour_or_no_show' => 'Tour / no show',
    ];

    /**
     * @param  array<string, string>  $columnMap  lead_field => csv_header
     * @return array{inserted: list<int>, inserted_count: int, duplicate_count: int, conflict_count: int, total_rows: int}
     */
    public function process(ImportBatch $batch, string $filePath, array $columnMap, string $leadType): array
    {
        if (! is_readable($filePath)) {
            throw new RuntimeException("Import file is not readable: {$filePath}");
        }

        $batch->update([
            'status' => ImportBatchStatus::Processing,
            'lead_type' => $leadType,
        ]);

        $rows = $this->readCsv($filePath);
        $headers = array_shift($rows) ?? [];

        if ($headers === []) {
            throw new RuntimeException('CSV file has no header row.');
        }

        $headerIndex = $this->buildHeaderIndex($headers);
        $insertedLeadIds = [];
        $duplicateCount = 0;
        $conflictCount = 0;
        $importedAt = now();

        foreach ($rows as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $attributes = $this->mapRow($row, $headerIndex, $columnMap, $leadType, $importedAt);

            if ($attributes['phone'] === null) {
                $duplicateCount++;

                continue;
            }

            $dedupe = $this->checkDedupe($batch->company_id, $attributes['external_lead_id'], $attributes['phone']);

            if ($dedupe === 'conflict') {
                $conflictCount++;

                continue;
            }

            if ($dedupe === 'duplicate') {
                $duplicateCount++;

                continue;
            }

            $lead = Lead::withoutGlobalScopes()->create([
                ...$attributes,
                'company_id' => $batch->company_id,
                'import_batch_id' => $batch->id,
                'status' => LeadStatus::Holding,
            ]);

            $insertedLeadIds[] = $lead->id;
        }

        $totalRows = count($rows);
        $insertedCount = count($insertedLeadIds);

        $batch->update([
            'status' => ImportBatchStatus::Completed,
            'imported_at' => $importedAt,
            'total_rows' => $totalRows,
            'inserted_count' => $insertedCount,
            'duplicate_count' => $duplicateCount,
            'conflict_count' => $conflictCount,
            'soft_score_pending' => $batch->run_soft_score ? $insertedCount : 0,
        ]);

        if ($batch->run_soft_score && $insertedLeadIds !== []) {
            foreach ($insertedLeadIds as $leadId) {
                SoftScoreLeadJob::dispatch($leadId, $batch->id);
            }
        }

        return [
            'inserted' => $insertedLeadIds,
            'inserted_count' => $insertedCount,
            'duplicate_count' => $duplicateCount,
            'conflict_count' => $conflictCount,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * @return list<list<string|null>>
     */
    private function readCsv(string $filePath): array
    {
        $file = new SplFileObject($filePath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $rows = [];

        foreach ($file as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = array_map(
                fn ($value) => is_string($value) ? trim($value) : (is_null($value) ? null : (string) $value),
                $row,
            );
        }

        return $rows;
    }

    /**
     * @param  list<string|null>  $headers
     * @return array<string, int>
     */
    private function buildHeaderIndex(array $headers): array
    {
        $index = [];

        foreach ($headers as $position => $header) {
            if ($header === null || $header === '') {
                continue;
            }

            $index[$this->normalizeHeader($header)] = $position;
        }

        return $index;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim($header));
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $headerIndex
     * @param  array<string, string>  $columnMap
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $headerIndex, array $columnMap, string $leadType, Carbon $importedAt): array
    {
        $knownFields = array_keys(self::KNOWN_IMPORT_FIELDS);

        $attributes = array_fill_keys($knownFields, null);
        $attributes['lead_type'] = $leadType;
        $attributes['imported_at'] = $importedAt;
        $attributes['extra_fields'] = [];
        $attributes['timezone'] = null;

        foreach ($columnMap as $leadField => $csvHeader) {
            if ($csvHeader === '') {
                continue;
            }

            $value = $this->valueAt($row, $headerIndex, $csvHeader);

            if ($value === null || $value === '') {
                continue;
            }

            if (str_starts_with($leadField, 'extra.')) {
                $attributes['extra_fields'][substr($leadField, 6)] = $value;

                continue;
            }

            if (in_array($leadField, $knownFields, true)) {
                $attributes[$leadField] = $value;
            }
        }

        $attributes['phone'] = PhoneNormalizer::normalize($attributes['phone']);
        $attributes['phone_2'] = PhoneNormalizer::normalize($attributes['phone_2']);
        $attributes['state'] = $attributes['state'] ? strtoupper(substr($attributes['state'], 0, 2)) : null;
        $attributes['timezone'] = TimezoneResolver::resolve(
            $attributes['state'],
            $attributes['zip'],
            $attributes['city'],
        );

        if ($attributes['extra_fields'] === []) {
            $attributes['extra_fields'] = null;
        }

        return $attributes;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $headerIndex
     */
    private function valueAt(array $row, array $headerIndex, string $csvHeader): ?string
    {
        $position = $headerIndex[$this->normalizeHeader($csvHeader)] ?? null;

        if ($position === null) {
            return null;
        }

        return $row[$position] ?? null;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function checkDedupe(int $companyId, ?string $externalLeadId, ?string $phone): string
    {
        $byExternalId = null;
        $byPhone = null;

        if ($externalLeadId) {
            $byExternalId = Lead::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('external_lead_id', $externalLeadId)
                ->first();
        }

        if ($phone) {
            $byPhone = Lead::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('phone', $phone)
                ->first();
        }

        if ($byExternalId && $byPhone && $byExternalId->id !== $byPhone->id) {
            return 'conflict';
        }

        if ($byExternalId || $byPhone) {
            return 'duplicate';
        }

        return 'new';
    }

    public function createBatch(
        int $companyId,
        string $sourceFilename,
        string $leadType,
        bool $runSoftScore,
    ): ImportBatch {
        return ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'source_filename' => $sourceFilename,
            'imported_at' => now(),
            'lead_type' => $leadType,
            'run_soft_score' => $runSoftScore,
            'status' => ImportBatchStatus::Pending,
        ]);
    }

    public function markFailed(ImportBatch $batch, string $message): void
    {
        $batch->update([
            'status' => ImportBatchStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
