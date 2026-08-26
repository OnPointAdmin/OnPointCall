<?php

namespace App\Services\Migration;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Support\CsvHeader;
use App\Support\PhoneNormalizer;
use App\Support\TimezoneResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use SplFileObject;

class LeadMasterMigrationService
{
    /**
     * @var array<string, Disposition>
     */
    private const DISPOSITION_LABELS = [
        'booked' => Disposition::Booked,
        'callback' => Disposition::Callback,
        'no answer' => Disposition::NoAnswer,
        'left vm' => Disposition::LeftVm,
        'not interested' => Disposition::NotInterested,
        'not qualified' => Disposition::NotQualified,
        'dnc' => Disposition::Dnc,
        'bad number' => Disposition::BadNumber,
        'wrong number' => Disposition::WrongNumber,
    ];

    /**
     * @var array<string, string>
     */
    private const LEAD_TYPE_MAP = [
        'standard' => 'standard',
        'tour no buy' => 'tnb',
    ];

    /**
     * Callback-only lists, keyed by lead type slug.
     *
     * @var array<string, string>
     */
    private const CALLBACK_LISTS = [
        'standard' => 'Standard - List A',
        'tnb' => 'TNB - List A',
    ];

    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly TimezoneResolver $timezoneResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function migrate(
        Company $company,
        string $filePath,
        bool $dryRun = false,
        bool $skipExisting = true,
    ): array {
        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = $file->fgetcsv();

        if (! is_array($headers) || $headers === []) {
            throw new \RuntimeException('CSV file has no header row.');
        }

        $headers = array_map(fn (mixed $header): string => trim((string) $header), $headers);
        $headerIndex = $this->buildHeaderIndex($headers);

        $lists = $this->resolveCallingLists($company->id);
        $usersByName = $this->resolveUsersByName($company->id);

        $stats = [
            'total_rows' => 0,
            'would_insert' => 0,
            'inserted' => 0,
            'skipped_duplicate' => 0,
            'skipped_invalid_phone' => 0,
            'status_counts' => [],
            'disposition_counts' => [],
            'lead_type_counts' => [],
            'unmatched_agents' => [],
            'bad_dates' => 0,
            'callbacks_without_agent' => 0,
            'invalid_phone_2' => 0,
        ];

        $skippedCsvPath = storage_path('app/imports/leadmaster-skipped.csv');
        $skippedHandle = null;

        if (! $dryRun) {
            if (! is_dir(dirname($skippedCsvPath))) {
                mkdir(dirname($skippedCsvPath), 0755, true);
            }

            $skippedHandle = fopen($skippedCsvPath, 'w');
            fputcsv($skippedHandle, array_merge(['skip_reason'], $headers));
        }

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if (! is_array($row) || $this->isBlankRow($row)) {
                continue;
            }

            $stats['total_rows']++;
            $phone = $this->phoneNormalizer->normalize($this->value($row, $headerIndex, 'Phone') ?? '');

            if ($phone === null) {
                $stats['skipped_invalid_phone']++;
                $this->writeSkippedRow($skippedHandle, $headers, $row, 'invalid_phone');

                continue;
            }

            $externalId = trim($this->value($row, $headerIndex, 'Lead ID') ?? '');

            if ($skipExisting && $this->leadExists($company->id, $phone, $externalId !== '' ? $externalId : null)) {
                $stats['skipped_duplicate']++;
                $this->writeSkippedRow($skippedHandle, $headers, $row, 'duplicate');

                continue;
            }

            $phone2Raw = trim($this->value($row, $headerIndex, 'Phone 2') ?? '');
            $phone2 = $phone2Raw !== '' ? $this->phoneNormalizer->normalize($phone2Raw) : null;

            if ($phone2Raw !== '' && $phone2 === null) {
                $stats['invalid_phone_2']++;
            }

            $leadType = $this->mapLeadType($this->value($row, $headerIndex, 'Lead Type') ?? '');
            $disposition = $this->mapDisposition($this->value($row, $headerIndex, 'Disposition') ?? '');
            $sheetStatus = trim($this->value($row, $headerIndex, 'Status') ?? '');
            $assignedAgentName = trim($this->value($row, $headerIndex, 'Assigned Agent') ?? '');
            $lastCalledByName = trim($this->value($row, $headerIndex, 'Last Called By') ?? '');
            $agentName = $assignedAgentName !== '' ? $assignedAgentName : $lastCalledByName;
            $actor = $agentName !== '' ? ($usersByName[strtolower($agentName)] ?? null) : null;

            if ($agentName !== '' && $actor === null) {
                $stats['unmatched_agents'][$agentName] = ($stats['unmatched_agents'][$agentName] ?? 0) + 1;
            }

            $submitDate = $this->parseSheetDate($this->value($row, $headerIndex, 'Lead Submit Date'), $stats);
            $batchDate = $this->parseSheetDate($this->value($row, $headerIndex, 'Batch Date'), $stats);
            $tourDate = $this->parseSheetDate($this->value($row, $headerIndex, 'Tour Date'), $stats);

            $state = strtoupper(substr(trim($this->value($row, $headerIndex, 'State') ?? ''), 0, 2));
            $zip = trim($this->value($row, $headerIndex, 'Zip') ?? '');
            $city = trim($this->value($row, $headerIndex, 'City') ?? '');

            [$status, $listId, $callbackOwnerId, $callbackAt] = $this->resolveStatusAndList(
                $disposition,
                $leadType,
                $lists,
                $actor,
                $stats,
            );

            $attemptCount = max(0, (int) trim($this->value($row, $headerIndex, 'Total Call Count') ?? '0'));

            $note = trim($this->value($row, $headerIndex, 'Notes') ?? '');
            $callbackAtRaw = trim($this->value($row, $headerIndex, 'Callback At') ?? '');

            if ($callbackAtRaw !== '' && $this->parseSheetDate($callbackAtRaw, $stats) === null) {
                $note = $note !== '' ? "{$note} | Callback note: {$callbackAtRaw}" : "Callback note: {$callbackAtRaw}";
            }

            $leadAttributes = [
                'company_id' => $company->id,
                'phone' => $phone,
                'phone_2' => $phone2,
                'first_name' => $this->nullable($this->value($row, $headerIndex, 'First Name')),
                'last_name' => $this->nullable($this->value($row, $headerIndex, 'Last Name')),
                'first_name_2' => $this->nullable($this->value($row, $headerIndex, 'First Name 2')),
                'last_name_2' => $this->nullable($this->value($row, $headerIndex, 'Last Name 2')),
                'address' => $this->nullable($this->value($row, $headerIndex, 'Address')),
                'address_2' => $this->nullable($this->value($row, $headerIndex, 'Address 2')),
                'city' => $this->nullable($city),
                'state' => $state !== '' ? $state : null,
                'zip' => $this->nullable($zip),
                'email' => $this->nullable($this->value($row, $headerIndex, 'Email')),
                'age_range' => $this->nullable($this->value($row, $headerIndex, 'Age Range')),
                'annual_income' => $this->nullable($this->value($row, $headerIndex, 'Annual Income')),
                'marital_status' => $this->nullable($this->value($row, $headerIndex, 'Marital Status')),
                'gender' => $this->nullable($this->value($row, $headerIndex, 'Gender')),
                'home_owner' => $this->nullable($this->value($row, $headerIndex, 'Home Owner')),
                'original_lead_submit_date' => $submitDate,
                'venue' => $this->nullable($this->value($row, $headerIndex, 'Venue')),
                'event' => $this->nullable($this->value($row, $headerIndex, 'Event')),
                'partner_list' => $this->nullable($this->value($row, $headerIndex, 'Partner List')),
                'file_name' => $this->nullable($this->value($row, $headerIndex, 'File Name')),
                'booking_id' => $this->nullable($this->value($row, $headerIndex, 'Booking Id')),
                'tour_location' => $this->nullable($this->value($row, $headerIndex, 'Tour Location')),
                'tour_date' => $tourDate,
                'premiums' => $this->nullable($this->value($row, $headerIndex, 'Premiums')),
                'tour_result' => $this->nullable($this->value($row, $headerIndex, 'Tour Result')),
                'tour_or_no_show' => $this->nullable($this->value($row, $headerIndex, 'Tour Or No Show')),
                'external_lead_id' => $externalId !== '' ? $externalId : null,
                'timezone' => $this->timezoneResolver->resolve($state !== '' ? $state : null, $zip, $city),
                'status' => $status,
                'lead_type' => $leadType,
                'calling_list_id' => $listId,
                'attempt_count' => $attemptCount,
                'callback_owner_id' => $callbackOwnerId,
                'callback_at' => $callbackAt,
                'imported_at' => now(),
                'queue_rank' => 0,
            ];

            $stats['status_counts'][$status->value] = ($stats['status_counts'][$status->value] ?? 0) + 1;
            $stats['lead_type_counts'][$leadType] = ($stats['lead_type_counts'][$leadType] ?? 0) + 1;

            $dispositionKey = $disposition?->value ?? '(none)';
            $stats['disposition_counts'][$dispositionKey] = ($stats['disposition_counts'][$dispositionKey] ?? 0) + 1;

            if ($dryRun) {
                $stats['would_insert']++;

                continue;
            }

            DB::transaction(function () use ($company, $leadAttributes, $disposition, $sheetStatus, $actor, $note, $batchDate): void {
                $lead = Lead::withoutGlobalScopes()->create($leadAttributes);

                if ($disposition === null) {
                    return;
                }

                $payload = [
                    'source' => 'leadmaster_migration',
                    'disposition' => $disposition->value,
                ];

                if ($sheetStatus !== '') {
                    $payload['sheet_status'] = $sheetStatus;
                }

                if ($note !== '') {
                    $payload['note'] = $note;
                }

                LeadHistory::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'lead_id' => $lead->id,
                    'actor_id' => $actor?->id,
                    'event_type' => LeadHistoryType::Disposition,
                    'occurred_at' => $this->resolveDispositionOccurredAt($batchDate, $leadAttributes['timezone']),
                    'payload' => $payload,
                ]);
            });

            $stats['inserted']++;
        }

        if ($skippedHandle !== null) {
            fclose($skippedHandle);
        }

        ksort($stats['unmatched_agents']);

        return $stats;
    }

    /**
     * Fill blank external_lead_id values from a LeadMaster CSV, matching by phone.
     *
     * @return array{csv_rows: int, updated: int, already_set: int, skipped_missing_lead: int, skipped_conflict: int, skipped_no_id: int, skipped_invalid_phone: int}
     */
    public function backfillExternalLeadIds(Company $company, string $filePath): array
    {
        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = $file->fgetcsv();

        if (! is_array($headers) || $headers === []) {
            throw new \RuntimeException('CSV file has no header row.');
        }

        $headers = array_map(fn (mixed $header): string => trim((string) $header), $headers);
        $headerIndex = $this->buildHeaderIndex($headers);

        $stats = [
            'csv_rows' => 0,
            'updated' => 0,
            'already_set' => 0,
            'skipped_missing_lead' => 0,
            'skipped_conflict' => 0,
            'skipped_no_id' => 0,
            'skipped_invalid_phone' => 0,
        ];

        $idsByPhone = [];

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if (! is_array($row) || $this->isBlankRow($row)) {
                continue;
            }

            $stats['csv_rows']++;
            $phone = $this->phoneNormalizer->normalize($this->value($row, $headerIndex, 'Phone') ?? '');

            if ($phone === null) {
                $stats['skipped_invalid_phone']++;

                continue;
            }

            $externalId = trim($this->value($row, $headerIndex, 'Lead ID') ?? '');

            if ($externalId === '') {
                $stats['skipped_no_id']++;

                continue;
            }

            $idsByPhone[$phone] = $externalId;
        }

        if ($idsByPhone === []) {
            return $stats;
        }

        $leadsByPhone = Lead::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('phone', array_keys($idsByPhone))
            ->get()
            ->keyBy('phone');

        $takenIds = Lead::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNotNull('external_lead_id')
            ->pluck('id', 'external_lead_id');

        foreach ($idsByPhone as $phone => $externalId) {
            $lead = $leadsByPhone->get($phone);

            if ($lead === null) {
                $stats['skipped_missing_lead']++;

                continue;
            }

            if ($lead->external_lead_id === $externalId) {
                $stats['already_set']++;

                continue;
            }

            if ($lead->external_lead_id) {
                $stats['already_set']++;

                continue;
            }

            $ownerLeadId = $takenIds[$externalId] ?? null;

            if ($ownerLeadId !== null && (int) $ownerLeadId !== (int) $lead->id) {
                $stats['skipped_conflict']++;

                continue;
            }

            $lead->update(['external_lead_id' => $externalId]);
            $takenIds[$externalId] = $lead->id;
            $stats['updated']++;
        }

        return $stats;
    }

    /**
     * @return array{updated: int, skipped: int, fallback_now: int}
     */
    public function backfillDispositionDates(Company $company, string $filePath): array
    {
        $datesByExternalId = [];
        $datesByPhone = [];
        $stats = ['updated' => 0, 'skipped' => 0, 'fallback_now' => 0];

        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = array_map(fn (mixed $header): string => trim((string) $header), $file->fgetcsv() ?: []);
        $headerIndex = $this->buildHeaderIndex($headers);
        $parseStats = ['bad_dates' => 0];

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if (! is_array($row) || $this->isBlankRow($row)) {
                continue;
            }

            if ($this->nullable($this->value($row, $headerIndex, 'Disposition')) === null) {
                continue;
            }

            $batchDate = $this->parseSheetDate($this->value($row, $headerIndex, 'Batch Date'), $parseStats);
            $externalId = trim($this->value($row, $headerIndex, 'Lead ID') ?? '');
            $phone = $this->phoneNormalizer->normalize($this->value($row, $headerIndex, 'Phone') ?? '');

            if ($externalId !== '') {
                $datesByExternalId[$externalId] = $batchDate;
            }

            if ($phone !== null) {
                $datesByPhone[$phone] = $batchDate;
            }
        }

        $histories = LeadHistory::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('event_type', LeadHistoryType::Disposition)
            ->where('payload->source', 'leadmaster_migration')
            ->with(['lead' => fn ($query) => $query->withoutGlobalScopes()])
            ->get();

        foreach ($histories as $history) {
            $lead = $history->lead;

            if (! $lead) {
                $stats['skipped']++;

                continue;
            }

            $batchDate = null;

            if ($lead->external_lead_id) {
                $batchDate = $datesByExternalId[$lead->external_lead_id] ?? null;
            }

            if ($batchDate === null && $lead->phone) {
                $batchDate = $datesByPhone[$lead->phone] ?? null;
            }

            if ($batchDate === null) {
                $stats['fallback_now']++;

                continue;
            }

            $history->update([
                'occurred_at' => $this->resolveDispositionOccurredAt($batchDate, $lead->timezone),
            ]);

            $stats['updated']++;
        }

        return $stats;
    }

    private function resolveDispositionOccurredAt(?string $batchDate, ?string $timezone): Carbon
    {
        if ($batchDate !== null) {
            return Carbon::parse($batchDate, $timezone ?: 'America/New_York')->endOfDay()->utc();
        }

        return now();
    }

    /**
     * @return array{standard: int, tnb: int}
     */
    private function resolveCallingLists(int $companyId): array
    {
        $lists = CallingList::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('name', array_values(self::CALLBACK_LISTS))
            ->get()
            ->keyBy('name');

        $missing = [];

        foreach (self::CALLBACK_LISTS as $name) {
            if ($lists->get($name) === null) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException(
                'Missing calling lists required for callbacks: '.implode(', ', $missing)
            );
        }

        return [
            'standard' => (int) $lists->get(self::CALLBACK_LISTS['standard'])->id,
            'tnb' => (int) $lists->get(self::CALLBACK_LISTS['tnb'])->id,
        ];
    }

    /**
     * @return array<string, User>
     */
    private function resolveUsersByName(int $companyId): array
    {
        $users = User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get();

        $byName = [];

        foreach ($users as $user) {
            $byName[strtolower(trim($user->name))] = $user;
        }

        return $byName;
    }

    private function leadExists(int $companyId, string $phone, ?string $externalId): bool
    {
        return Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($phone, $externalId): void {
                $query->where('phone', $phone);

                if ($externalId) {
                    $query->orWhere('external_lead_id', $externalId);
                }
            })
            ->exists();
    }

    /**
     * @param  array{standard: int, tnb: int}  $lists
     * @return array{0: LeadStatus, 1: ?int, 2: ?int, 3: ?Carbon}
     */
    private function resolveStatusAndList(
        ?Disposition $disposition,
        string $leadType,
        array $lists,
        ?User $actor,
        array &$stats,
    ): array {
        if ($disposition !== Disposition::Callback) {
            return [LeadStatus::Holding, null, null, null];
        }

        $listId = $lists[$leadType] ?? null;

        if ($listId === null) {
            throw new \RuntimeException("No callback list mapped for lead type [{$leadType}].");
        }

        return $this->resolveCallback($listId, $actor, $stats);
    }

    /**
     * @return array{0: LeadStatus, 1: int, 2: ?int, 3: Carbon}
     */
    private function resolveCallback(int $listId, ?User $actor, array &$stats): array
    {
        if ($actor === null) {
            $stats['callbacks_without_agent']++;
        }

        return [LeadStatus::Callback, $listId, $actor?->id, now()];
    }

    private function mapLeadType(string $value): string
    {
        $key = strtolower(trim($value));

        return self::LEAD_TYPE_MAP[$key] ?? 'standard';
    }

    private function mapDisposition(string $value): ?Disposition
    {
        $key = strtolower(trim($value));

        if ($key === '') {
            return null;
        }

        return self::DISPOSITION_LABELS[$key] ?? null;
    }

    private function parseSheetDate(?string $value, array &$stats): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || str_contains($value, '#')) {
            if ($value !== '' && str_contains($value, '#')) {
                $stats['bad_dates']++;
            }

            return null;
        }

        if (ctype_digit($value)) {
            try {
                return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            } catch (\Throwable) {
                $stats['bad_dates']++;

                return null;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            $stats['bad_dates']++;

            return null;
        }
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $headerIndex
     */
    private function value(array $row, array $headerIndex, string $header): ?string
    {
        $position = $headerIndex[$this->normalizeHeader($header)] ?? null;

        if ($position === null) {
            return null;
        }

        $value = $row[$position] ?? null;

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    private function buildHeaderIndex(array $headers): array
    {
        $index = [];

        foreach ($headers as $position => $header) {
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
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  resource|null  $skippedHandle
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     */
    private function writeSkippedRow($skippedHandle, array $headers, array $row, string $reason): void
    {
        if ($skippedHandle === null) {
            return;
        }

        fputcsv($skippedHandle, array_merge([$reason], $row));
    }
}
