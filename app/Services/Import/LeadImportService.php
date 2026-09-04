<?php

namespace App\Services\Import;

use App\Enums\DncStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportSkipReason;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Jobs\DncScrubJob;
use App\Jobs\QualifyLeadJob;
use App\Jobs\RndLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\ImportBatch;
use App\Models\ImportBatchSkippedRow;
use App\Models\ImportMapping;
use App\Models\Lead;
use App\Services\SoftScore\SoftScoreService;
use App\Support\CsvHeader;
use App\Support\PhoneNormalizer;
use App\Support\TimezoneResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SplFileObject;
use Throwable;

class LeadImportService
{
    /**
     * @var array<string, string>
     */
    public const KNOWN_IMPORT_FIELDS = [
        'phone' => 'Phone',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'first_name_2' => 'First name 2',
        'last_name_2' => 'Last name 2',
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
        'soft_score_checked_at' => 'Soft Score last checked',
        'soft_score_code' => 'Soft Score code',
        'booking_id' => 'Booking ID',
        'phone_2' => 'Phone 2',
        'address_2' => 'Address 2',
        'tour_location' => 'Tour location',
        'tour_date_start' => 'Tour date start',
        'tour_date' => 'Tour date',
        'premiums' => 'Premiums',
        'tour_result' => 'Tour result',
        'tour_or_no_show' => 'Tour / no show',
    ];

    /**
     * @param  array<string, string>  $columnMap  lead_field => csv_header
     * @return array{inserted: list<int>, inserted_count: int, updated_count: int, duplicate_count: int, conflict_count: int, total_rows: int}
     */
    public function process(ImportBatch $batch, string $filePath, array $columnMap, string $leadType): array
    {
        if (! is_readable($filePath)) {
            throw new RuntimeException("Import file is not readable: {$filePath}");
        }

        $batch->update([
            'status' => ImportBatchStatus::Processing,
            'lead_type' => $leadType,
            'source_storage_path' => $batch->source_storage_path ?: $filePath,
            'column_map' => $batch->column_map ?: $columnMap,
        ]);

        $rows = $this->readCsv($filePath);
        $headers = array_shift($rows) ?? [];

        if ($headers === []) {
            throw new RuntimeException('CSV file has no header row.');
        }

        $headerIndex = $this->buildHeaderIndex($headers);
        $insertedLeadIds = [];
        $updatedLeadIds = [];
        $softScoreJobLeadIds = [];
        $softScoreRecentCount = 0;
        $rndJobLeadIds = [];
        $qualificationJobLeadIds = [];
        $dncJobLeadIds = [];
        $duplicateCount = 0;
        $conflictCount = 0;
        $importedAt = now();
        $softScoreService = app(SoftScoreService::class);

        foreach ($rows as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $attributes = $this->mapRow($row, $headerIndex, $columnMap, $leadType, $importedAt);

            if ($attributes['phone'] === null) {
                $duplicateCount++;
                $this->recordSkippedRow($batch, $attributes, ImportSkipReason::InvalidPhone);

                continue;
            }

            $dedupe = $this->resolveDedupe($batch->company_id, $attributes['external_lead_id'], $attributes['phone']);

            if ($dedupe['status'] === 'conflict') {
                $conflictCount++;
                $this->recordSkippedRow($batch, $attributes, ImportSkipReason::Conflict, $dedupe['lead'] ?? null);

                continue;
            }

            if ($dedupe['status'] === 'duplicate') {
                $existing = $dedupe['lead'];

                if ($existing && (int) $existing->import_batch_id === (int) $batch->id) {
                    $insertedLeadIds[] = $existing->id;
                    $this->requeuePendingChecks(
                        $existing,
                        $batch,
                        $softScoreJobLeadIds,
                        $rndJobLeadIds,
                        $qualificationJobLeadIds,
                        $dncJobLeadIds,
                    );

                    continue;
                }

                $duplicateCount++;
                $this->recordSkippedRow($batch, $attributes, ImportSkipReason::Duplicate, $existing);

                continue;
            }

            if ($dedupe['status'] === 'recoverable') {
                $lead = $dedupe['lead'];
                $queued = $this->updateRecoverableLead($lead, $batch, $attributes);

                $updatedLeadIds[] = $lead->id;
                $softScoreJobLeadIds = [...$softScoreJobLeadIds, ...$queued['soft_score']];
                $softScoreRecentCount += $queued['soft_score_recent'];
                $rndJobLeadIds = [...$rndJobLeadIds, ...$queued['rnd']];
                $qualificationJobLeadIds = [...$qualificationJobLeadIds, ...$queued['qualification']];
                $dncJobLeadIds = [...$dncJobLeadIds, ...$queued['dnc']];

                continue;
            }

            $leadAttributes = [
                ...$attributes,
                'company_id' => $batch->company_id,
                'import_batch_id' => $batch->id,
                'status' => LeadStatus::Holding,
            ];

            if ($batch->run_rnd_check) {
                $leadAttributes['rnd_status'] = RndStatus::Pending;
            }

            $queueSoftScore = false;
            $markSoftScoreRecent = false;
            if ($batch->run_soft_score) {
                if ($softScoreService->shouldRunFor(
                    $attributes['soft_score_code'] ?? null,
                    $attributes['soft_score_checked_at'] ?? null,
                )) {
                    $leadAttributes['soft_score_status'] = SoftScoreStatus::Pending;
                    $queueSoftScore = true;
                } else {
                    $markSoftScoreRecent = true;
                    $softScoreRecentCount++;
                }
            }

            if ($batch->run_qualification) {
                $leadAttributes['qualification_status'] = QualificationStatus::Pending;
            }

            if ($batch->run_dnc_check) {
                $leadAttributes['dnc_status'] = DncStatus::Pending;
            }

            $lead = Lead::withoutGlobalScopes()->create($leadAttributes);

            $insertedLeadIds[] = $lead->id;

            if ($queueSoftScore) {
                $softScoreJobLeadIds[] = $lead->id;
            } elseif ($markSoftScoreRecent) {
                $softScoreService->markRecent($lead);
            }

            if ($batch->run_rnd_check) {
                $rndJobLeadIds[] = $lead->id;
            }

            if ($batch->run_qualification) {
                $qualificationJobLeadIds[] = $lead->id;
            }

            if ($batch->run_dnc_check) {
                $dncJobLeadIds[] = $lead->id;
            }
        }

        $totalRows = count($rows);
        $insertedCount = count($insertedLeadIds);
        $updatedCount = count($updatedLeadIds);

        $batch->update([
            'status' => ImportBatchStatus::Completed,
            'imported_at' => $importedAt,
            'total_rows' => $totalRows,
            'inserted_count' => $insertedCount,
            'updated_count' => $updatedCount,
            'duplicate_count' => $duplicateCount,
            'conflict_count' => $conflictCount,
            'soft_score_pending' => count($softScoreJobLeadIds),
            'soft_score_qualified' => $softScoreRecentCount,
            'rnd_pending' => count($rndJobLeadIds),
            'qualification_pending' => count($qualificationJobLeadIds),
            'dnc_pending' => count($dncJobLeadIds),
        ]);

        $this->dispatchImportCheckJobs(
            $batch->id,
            $softScoreJobLeadIds,
            $rndJobLeadIds,
            $qualificationJobLeadIds,
            $dncJobLeadIds,
        );

        return [
            'inserted' => $insertedLeadIds,
            'inserted_count' => $insertedCount,
            'updated_count' => $updatedCount,
            'duplicate_count' => $duplicateCount,
            'conflict_count' => $conflictCount,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Soft Score must run (and update the lead) before Qualification when both are queued
     * for the same lead, so qualificationCode is available for the Salesforce payload.
     * SoftScoreLeadJob dispatches QualifyLeadJob after scoring when qualification is pending.
     *
     * @param  list<int>  $softScoreJobLeadIds
     * @param  list<int>  $rndJobLeadIds
     * @param  list<int>  $qualificationJobLeadIds
     * @param  list<int>  $dncJobLeadIds
     */
    private function dispatchImportCheckJobs(
        int $batchId,
        array $softScoreJobLeadIds,
        array $rndJobLeadIds,
        array $qualificationJobLeadIds,
        array $dncJobLeadIds,
    ): void {
        $softScoreSet = array_fill_keys($softScoreJobLeadIds, true);
        $qualificationSet = array_fill_keys($qualificationJobLeadIds, true);

        foreach ($softScoreJobLeadIds as $leadId) {
            SoftScoreLeadJob::dispatch(
                $leadId,
                $batchId,
                null,
                isset($qualificationSet[$leadId]),
            );
        }

        foreach ($rndJobLeadIds as $leadId) {
            RndLeadJob::dispatch($leadId, $batchId);
        }

        foreach ($qualificationJobLeadIds as $leadId) {
            // SoftScoreLeadJob will queue qualification after soft_score_code is saved.
            if (isset($softScoreSet[$leadId])) {
                continue;
            }

            QualifyLeadJob::dispatch($leadId, $batchId);
        }

        DncScrubJob::dispatchForLeadIds($dncJobLeadIds, $batchId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{soft_score: list<int>, soft_score_recent: int, rnd: list<int>, qualification: list<int>, dnc: list<int>}
     */
    private function updateRecoverableLead(
        Lead $lead,
        ImportBatch $batch,
        array $attributes,
    ): array {
        $oldBatchId = $lead->import_batch_id;
        $oldSoftScore = $lead->soft_score_status;
        $oldRnd = $lead->rnd_status;
        $oldQualification = $lead->qualification_status;
        $oldDnc = $lead->dnc_status;

        $retrySoftScore = $batch->run_soft_score && $oldSoftScore === SoftScoreStatus::Error;
        $retryRnd = $batch->run_rnd_check && $oldRnd === RndStatus::Error;
        $retryQualification = $batch->run_qualification && $oldQualification === QualificationStatus::Error;
        $retryDnc = $batch->run_dnc_check && $oldDnc === DncStatus::Error;

        $fieldUpdates = array_filter(
            $attributes,
            fn (mixed $value): bool => $value !== null,
        );

        $updates = [
            ...$fieldUpdates,
            'import_batch_id' => $batch->id,
        ];

        if ($retrySoftScore) {
            $updates['soft_score_status'] = SoftScoreStatus::Pending;
            $updates['soft_score_last_error'] = null;
        }

        if ($retryRnd) {
            $updates['rnd_status'] = RndStatus::Pending;
            $updates['rnd_last_error'] = null;
        }

        if ($retryQualification) {
            $updates['qualification_status'] = QualificationStatus::Pending;
            $updates['qualification_last_error'] = null;
        }

        if ($retryDnc) {
            $updates['dnc_status'] = DncStatus::Pending;
            $updates['dnc_last_error'] = null;
        }

        DB::transaction(function () use ($lead, $updates, $oldBatchId, $retrySoftScore, $retryRnd, $retryQualification, $retryDnc): void {
            if ($oldBatchId) {
                if ($retrySoftScore) {
                    $this->decrementSoftScoreCounter($oldBatchId, SoftScoreStatus::Error);
                }

                if ($retryRnd) {
                    $this->decrementRndCounter($oldBatchId, RndStatus::Error);
                }

                if ($retryQualification) {
                    $this->decrementQualificationCounter($oldBatchId, QualificationStatus::Error);
                }

                if ($retryDnc) {
                    $this->decrementDncCounter($oldBatchId, DncStatus::Error);
                }
            }

            $lead->update($updates);
        });

        return [
            'soft_score' => $retrySoftScore ? [$lead->id] : [],
            'soft_score_recent' => 0,
            'rnd' => $retryRnd ? [$lead->id] : [],
            'qualification' => $retryQualification ? [$lead->id] : [],
            'dnc' => $retryDnc ? [$lead->id] : [],
        ];
    }

    private function decrementSoftScoreCounter(int $batchId, SoftScoreStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = match ($status) {
            SoftScoreStatus::Complete, SoftScoreStatus::Recent => ['soft_score_qualified' => max(0, $batch->soft_score_qualified - 1)],
            SoftScoreStatus::Error => ['soft_score_error' => max(0, $batch->soft_score_error - 1)],
            SoftScoreStatus::Pending => ['soft_score_pending' => max(0, $batch->soft_score_pending - 1)],
        };

        $batch->update($updates);
    }

    private function decrementRndCounter(int $batchId, RndStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = match ($status) {
            RndStatus::Clear => ['rnd_clear' => max(0, $batch->rnd_clear - 1)],
            RndStatus::Reassigned => ['rnd_reassigned' => max(0, $batch->rnd_reassigned - 1)],
            RndStatus::NoData => ['rnd_no_data' => max(0, $batch->rnd_no_data - 1)],
            RndStatus::Error => ['rnd_error' => max(0, $batch->rnd_error - 1)],
            RndStatus::Pending => ['rnd_pending' => max(0, $batch->rnd_pending - 1)],
        };

        $batch->update($updates);
    }

    private function decrementQualificationCounter(int $batchId, QualificationStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = match ($status) {
            QualificationStatus::Qualified => ['qualification_qualified' => max(0, $batch->qualification_qualified - 1)],
            QualificationStatus::NotQualified => ['qualification_not_qualified' => max(0, $batch->qualification_not_qualified - 1)],
            QualificationStatus::Error => ['qualification_error' => max(0, $batch->qualification_error - 1)],
            QualificationStatus::Pending => ['qualification_pending' => max(0, $batch->qualification_pending - 1)],
        };

        $batch->update($updates);
    }

    private function decrementDncCounter(int $batchId, DncStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = match ($status) {
            DncStatus::Clear => ['dnc_clear' => max(0, $batch->dnc_clear - 1)],
            DncStatus::Hit => ['dnc_hit' => max(0, $batch->dnc_hit - 1)],
            DncStatus::Invalid => ['dnc_invalid' => max(0, $batch->dnc_invalid - 1)],
            DncStatus::Error => ['dnc_error' => max(0, $batch->dnc_error - 1)],
            DncStatus::Pending => ['dnc_pending' => max(0, $batch->dnc_pending - 1)],
        };

        $batch->update($updates);
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
                fn ($value) => $this->toUtf8($value),
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
        return CsvHeader::normalize($header);
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

        if ($attributes['phone'] === null && $attributes['phone_2'] !== null) {
            $attributes['phone'] = $attributes['phone_2'];
            $attributes['phone_2'] = null;
        }

        $attributes['state'] = $attributes['state'] ? strtoupper(substr($attributes['state'], 0, 2)) : null;
        $attributes['timezone'] = TimezoneResolver::resolve(
            $attributes['state'],
            $attributes['zip'],
            $attributes['city'],
        );
        $attributes['soft_score_checked_at'] = $this->parseDateTime($attributes['soft_score_checked_at'] ?? null);

        if ($attributes['extra_fields'] === []) {
            $attributes['extra_fields'] = null;
        }

        return $attributes;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordSkippedRow(
        ImportBatch $batch,
        array $attributes,
        ImportSkipReason $reason,
        ?Lead $existingLead = null,
    ): void {
        $duplicateSkip = ImportBatchSkippedRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('reason', $reason);

        if (($attributes['phone'] ?? null) !== null) {
            $duplicateSkip->where('phone', $attributes['phone']);
        } elseif (($attributes['external_lead_id'] ?? null) !== null) {
            $duplicateSkip->where('external_lead_id', $attributes['external_lead_id']);
        }

        if ($duplicateSkip->exists()) {
            return;
        }

        ImportBatchSkippedRow::query()->create([
            'import_batch_id' => $batch->id,
            'existing_lead_id' => $existingLead?->id,
            'reason' => $reason,
            'matched_on' => $this->matchedOn($existingLead, $attributes),
            'phone' => $attributes['phone'] ?? null,
            'first_name' => $attributes['first_name'] ?? null,
            'last_name' => $attributes['last_name'] ?? null,
            'external_lead_id' => $attributes['external_lead_id'] ?? null,
        ]);
    }

    /**
     * @param  list<int>  $softScoreJobLeadIds
     * @param  list<int>  $rndJobLeadIds
     * @param  list<int>  $qualificationJobLeadIds
     * @param  list<int>  $dncJobLeadIds
     */
    private function requeuePendingChecks(
        Lead $lead,
        ImportBatch $batch,
        array &$softScoreJobLeadIds,
        array &$rndJobLeadIds,
        array &$qualificationJobLeadIds,
        array &$dncJobLeadIds,
    ): void {
        if ($batch->run_soft_score && $lead->soft_score_status === SoftScoreStatus::Pending) {
            $softScoreJobLeadIds[] = $lead->id;
        }

        if ($batch->run_rnd_check && $lead->rnd_status === RndStatus::Pending) {
            $rndJobLeadIds[] = $lead->id;
        }

        if ($batch->run_qualification && $lead->qualification_status === QualificationStatus::Pending) {
            $qualificationJobLeadIds[] = $lead->id;
        }

        if ($batch->run_dnc_check && $lead->dnc_status === DncStatus::Pending) {
            $dncJobLeadIds[] = $lead->id;
        }
    }

    private function toUtf8(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim(is_string($value) ? $value : (string) $value);

        if ($string === '') {
            return $string;
        }

        if (mb_check_encoding($string, 'UTF-8')) {
            return $string;
        }

        $converted = @mb_convert_encoding($string, 'UTF-8', 'Windows-1252');

        if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
            return trim($converted);
        }

        $ignored = @iconv('Windows-1252', 'UTF-8//IGNORE', $string);

        return is_string($ignored) ? trim($ignored) : '';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function matchedOn(?Lead $existingLead, array $attributes): ?string
    {
        if (! $existingLead) {
            return null;
        }

        $phoneMatch = $existingLead->phone !== null
            && $attributes['phone'] !== null
            && $existingLead->phone === $attributes['phone'];
        $idMatch = $existingLead->external_lead_id !== null
            && ($attributes['external_lead_id'] ?? null) !== null
            && $existingLead->external_lead_id === $attributes['external_lead_id'];

        if ($phoneMatch && $idMatch) {
            return 'phone_and_id';
        }

        if ($idMatch) {
            return 'external_lead_id';
        }

        if ($phoneMatch) {
            return 'phone';
        }

        return null;
    }

    /**
     * @return array{status: 'new'|'duplicate'|'conflict'|'recoverable', lead: ?Lead}
     */
    private function resolveDedupe(int $companyId, ?string $externalLeadId, ?string $phone): array
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
            return ['status' => 'conflict', 'lead' => null];
        }

        $match = $byExternalId ?? $byPhone;

        if (! $match) {
            return ['status' => 'new', 'lead' => null];
        }

        if ($this->isRecoverableFailure($match)) {
            return ['status' => 'recoverable', 'lead' => $match];
        }

        return ['status' => 'duplicate', 'lead' => $match];
    }

    private function isRecoverableFailure(Lead $lead): bool
    {
        if ($lead->status !== LeadStatus::Holding) {
            return false;
        }

        return $lead->soft_score_status === SoftScoreStatus::Error
            || $lead->rnd_status === RndStatus::Error
            || $lead->qualification_status === QualificationStatus::Error
            || $lead->dnc_status === DncStatus::Error;
    }

    /**
     * Rebuild skipped-row records for a completed batch that has a duplicate
     * count but no Duplicates tab rows (e.g. imported before skipped rows were stored).
     */
    public function backfillSkippedRows(ImportBatch $batch): int
    {
        if ($batch->skippedRows()->exists()) {
            return $batch->skippedRows()->count();
        }

        $filePath = $this->resolveImportPath($batch);
        $columnMap = $this->resolveColumnMap($batch);

        if ($filePath === null || $columnMap === []) {
            return 0;
        }

        $rows = $this->readCsv($filePath);
        $headers = array_shift($rows) ?? [];

        if ($headers === []) {
            return 0;
        }

        $headerIndex = $this->buildHeaderIndex($headers);
        $importedAt = $batch->imported_at ?? now();
        $created = 0;

        foreach ($rows as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $attributes = $this->mapRow($row, $headerIndex, $columnMap, $batch->lead_type, $importedAt);

            if ($attributes['phone'] === null) {
                $this->recordSkippedRow($batch, $attributes, ImportSkipReason::InvalidPhone);
                $created++;

                continue;
            }

            $dedupe = $this->resolveDedupe($batch->company_id, $attributes['external_lead_id'], $attributes['phone']);

            if ($dedupe['status'] === 'conflict') {
                $this->recordSkippedRow($batch, $attributes, ImportSkipReason::Conflict, $dedupe['lead'] ?? null);
                $created++;

                continue;
            }

            if ($dedupe['status'] !== 'duplicate') {
                continue;
            }

            $existing = $dedupe['lead'];

            if ($existing && $existing->import_batch_id === $batch->id) {
                continue;
            }

            $this->recordSkippedRow($batch, $attributes, ImportSkipReason::Duplicate, $existing);
            $created++;
        }

        return $created;
    }

    /**
     * Re-apply the batch column map to leads already in this batch (matched by
     * phone) so a corrected mapping can fill fields the original import missed.
     *
     * @param  array<string, string>|null  $columnMap
     */
    public function refreshLeadFieldsFromSource(ImportBatch $batch, ?array $columnMap = null): int
    {
        if ($columnMap !== null && $columnMap !== []) {
            $batch->update(['column_map' => $columnMap]);
        }

        $filePath = $this->resolveImportPath($batch);
        $columnMap = $this->resolveColumnMap($batch);

        if ($filePath === null || $columnMap === []) {
            return 0;
        }

        $rows = $this->readCsv($filePath);
        $headers = array_shift($rows) ?? [];

        if ($headers === []) {
            return 0;
        }

        $headerIndex = $this->buildHeaderIndex($headers);
        $importedAt = $batch->imported_at ?? now();
        $refreshable = [
            'first_name',
            'last_name',
            'first_name_2',
            'last_name_2',
            'address',
            'address_2',
            'city',
            'state',
            'zip',
            'email',
            'venue',
            'event',
            'external_lead_id',
            'partner_list',
            'file_name',
            'age_range',
            'annual_income',
            'marital_status',
            'gender',
            'home_owner',
            'original_lead_submit_date',
            'booking_id',
            'phone_2',
            'tour_location',
            'tour_date_start',
            'tour_date',
            'premiums',
            'tour_result',
            'tour_or_no_show',
            'extra_fields',
            'timezone',
        ];
        $updated = 0;

        foreach ($rows as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $attributes = $this->mapRow($row, $headerIndex, $columnMap, $batch->lead_type, $importedAt);

            if ($attributes['phone'] === null) {
                continue;
            }

            $lead = Lead::withoutGlobalScopes()
                ->where('import_batch_id', $batch->id)
                ->where('phone', $attributes['phone'])
                ->first();

            if (! $lead) {
                continue;
            }

            $lead->update(array_intersect_key($attributes, array_flip($refreshable)));
            $updated++;
        }

        return $updated;
    }

    private function resolveImportPath(ImportBatch $batch): ?string
    {
        $candidates = array_filter([
            $batch->source_storage_path,
            $this->guessUploadPath($batch),
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && is_readable($path)) {
                return $path;
            }

            if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
                return Storage::disk('local')->path($path);
            }
        }

        return null;
    }

    private function guessUploadPath(ImportBatch $batch): ?string
    {
        $files = Storage::disk('local')->files('imports/uploads');
        $createdAt = $batch->created_at?->getTimestamp();

        if ($createdAt === null || $files === []) {
            return null;
        }

        $closest = null;
        $closestDelta = null;

        foreach ($files as $file) {
            $mtime = Storage::disk('local')->lastModified($file);
            $delta = abs($mtime - $createdAt);

            if ($delta > 120) {
                continue;
            }

            if ($closestDelta === null || $delta < $closestDelta) {
                $closest = $file;
                $closestDelta = $delta;
            }
        }

        return $closest;
    }

    /**
     * @return array<string, string>
     */
    private function resolveColumnMap(ImportBatch $batch): array
    {
        if (is_array($batch->column_map) && $batch->column_map !== []) {
            return $batch->column_map;
        }

        $mapping = ImportMapping::withoutGlobalScopes()
            ->where('company_id', $batch->company_id)
            ->where('is_default', true)
            ->first();

        return is_array($mapping?->column_map) ? $mapping->column_map : [];
    }

    public function createBatch(
        int $companyId,
        string $sourceFilename,
        string $leadType,
        bool $runSoftScore,
        bool $runRndCheck = false,
        bool $runQualification = false,
        bool $runDncCheck = false,
        bool $ignoreNationalDnc = false,
    ): ImportBatch {
        return ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'source_filename' => $sourceFilename,
            'imported_at' => now(),
            'lead_type' => $leadType,
            'run_soft_score' => $runSoftScore,
            'run_rnd_check' => $runRndCheck,
            'run_qualification' => $runQualification,
            'run_dnc_check' => $runDncCheck,
            'ignore_national_dnc' => $ignoreNationalDnc,
            'status' => ImportBatchStatus::Pending,
        ]);
    }

    public function markFailed(ImportBatch $batch, string $message): void
    {
        ImportBatch::withoutGlobalScopes()->whereKey($batch->id)->update([
            'status' => ImportBatchStatus::Failed,
            'error_message' => $this->toUtf8($message) ?: $message,
        ]);
    }
}
