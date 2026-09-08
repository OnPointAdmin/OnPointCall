<?php

namespace App\Services\Dashboard;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Models\DispositionDefinition;
use App\Models\LeadHistory;
use App\Models\LeadTypeDefinition;
use App\Support\CompanyTimezone;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CallDetailReportService
{
    public const PER_PAGE = 50;

    /**
     * @return array<string, array{label: string, table_default: bool, csv_default: bool, wrap?: bool}>
     */
    public static function columnDefinitions(): array
    {
        return [
            'called_at' => ['label' => 'Called At', 'table_default' => true, 'csv_default' => true],
            'rep' => ['label' => 'Rep', 'table_default' => true, 'csv_default' => true],
            'name' => ['label' => 'Name', 'table_default' => true, 'csv_default' => false],
            'first_name' => ['label' => 'First Name', 'table_default' => false, 'csv_default' => true],
            'last_name' => ['label' => 'Last Name', 'table_default' => false, 'csv_default' => true],
            'phone' => ['label' => 'Phone', 'table_default' => true, 'csv_default' => true],
            'phone_2' => ['label' => 'Phone 2', 'table_default' => false, 'csv_default' => true],
            'email' => ['label' => 'Email', 'table_default' => false, 'csv_default' => true],
            'disposition' => ['label' => 'Disposition', 'table_default' => true, 'csv_default' => true],
            'reason' => ['label' => 'Reason', 'table_default' => true, 'csv_default' => true],
            'note' => ['label' => 'Note', 'table_default' => false, 'csv_default' => true, 'wrap' => true],
            'callback_at' => ['label' => 'Callback At', 'table_default' => false, 'csv_default' => true],
            'calling_list' => ['label' => 'Calling List', 'table_default' => true, 'csv_default' => true],
            'lead_type' => ['label' => 'Lead Type', 'table_default' => false, 'csv_default' => true],
            'city' => ['label' => 'City', 'table_default' => false, 'csv_default' => true],
            'state' => ['label' => 'State', 'table_default' => false, 'csv_default' => true],
            'zip' => ['label' => 'Zip', 'table_default' => false, 'csv_default' => true],
            'venue' => ['label' => 'Venue', 'table_default' => true, 'csv_default' => true],
            'event' => ['label' => 'Event', 'table_default' => true, 'csv_default' => true],
            'partner_list' => ['label' => 'Partner List', 'table_default' => false, 'csv_default' => true, 'wrap' => true],
            'age_range' => ['label' => 'Age range', 'table_default' => false, 'csv_default' => false],
            'annual_income' => ['label' => 'Annual income', 'table_default' => false, 'csv_default' => false],
            'marital_status' => ['label' => 'Marital status', 'table_default' => false, 'csv_default' => false],
            'gender' => ['label' => 'Gender', 'table_default' => false, 'csv_default' => false],
            'home_owner' => ['label' => 'Homeowner', 'table_default' => false, 'csv_default' => false],
            'soft_score' => ['label' => 'Soft Score', 'table_default' => false, 'csv_default' => false],
            'qualified_partners' => ['label' => 'Qualified Partners', 'table_default' => false, 'csv_default' => false, 'wrap' => true],
            'qualification_status' => ['label' => 'Qualification', 'table_default' => false, 'csv_default' => false],
            'dnc_status' => ['label' => 'DNC', 'table_default' => false, 'csv_default' => false],
            'rnd_status' => ['label' => 'RND', 'table_default' => false, 'csv_default' => false],
            'address' => ['label' => 'Address', 'table_default' => false, 'csv_default' => false, 'wrap' => true],
            'address_2' => ['label' => 'Address 2', 'table_default' => false, 'csv_default' => false],
            'first_name_2' => ['label' => 'First name 2', 'table_default' => false, 'csv_default' => false],
            'last_name_2' => ['label' => 'Last name 2', 'table_default' => false, 'csv_default' => false],
            'original_lead_submit_date' => ['label' => 'Original submit date', 'table_default' => false, 'csv_default' => false],
            'tour_location' => ['label' => 'Tour location', 'table_default' => false, 'csv_default' => false],
            'tour_date_start' => ['label' => 'Tour date start', 'table_default' => false, 'csv_default' => false],
            'tour_date' => ['label' => 'Tour date', 'table_default' => false, 'csv_default' => false],
            'premiums' => ['label' => 'Premiums', 'table_default' => false, 'csv_default' => false],
            'tour_result' => ['label' => 'Tour result', 'table_default' => false, 'csv_default' => false],
            'tour_or_no_show' => ['label' => 'Tour / no show', 'table_default' => false, 'csv_default' => false],
            'file_name' => ['label' => 'Source file', 'table_default' => false, 'csv_default' => false],
            'external_lead_id' => ['label' => 'Lead ID', 'table_default' => false, 'csv_default' => true],
            'booking_id' => ['label' => 'Booking ID', 'table_default' => false, 'csv_default' => true],
            'status' => ['label' => 'Current Status', 'table_default' => false, 'csv_default' => true],
            'attempt_count' => ['label' => 'Attempt Count', 'table_default' => false, 'csv_default' => true],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function columnOptions(): array
    {
        return collect(self::columnDefinitions())
            ->mapWithKeys(fn (array $column, string $key): array => [$key => $column['label']])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function defaultTableColumnKeys(): array
    {
        return collect(self::columnDefinitions())
            ->filter(fn (array $column): bool => $column['table_default'])
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function defaultCsvColumnKeys(): array
    {
        return [
            'called_at',
            'rep',
            'disposition',
            'reason',
            'note',
            'callback_at',
            'calling_list',
            'lead_type',
            'first_name',
            'last_name',
            'phone',
            'phone_2',
            'email',
            'city',
            'state',
            'zip',
            'venue',
            'event',
            'partner_list',
            'external_lead_id',
            'booking_id',
            'status',
            'attempt_count',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allColumnKeys(): array
    {
        return array_keys(self::columnDefinitions());
    }

    /**
     * @param  list<string>|null  $columns
     * @return list<string>
     */
    public static function normalizeColumns(?array $columns, bool $fallbackToCsv = false): array
    {
        $known = array_keys(self::columnDefinitions());
        $selected = collect($columns ?? [])
            ->map(fn (mixed $key): string => is_string($key) ? $key : '')
            ->filter(fn (string $key): bool => in_array($key, $known, true))
            ->unique()
            ->values()
            ->all();

        if ($selected === []) {
            return $fallbackToCsv ? self::defaultCsvColumnKeys() : self::defaultTableColumnKeys();
        }

        return $selected;
    }

    /**
     * @param  list<string>|null  $columns
     * @return list<array{key: string, label: string, wrap: bool}>
     */
    public static function visibleColumnDefs(?array $columns, bool $fallbackToCsv = false): array
    {
        $definitions = self::columnDefinitions();

        return array_map(
            fn (string $key): array => [
                'key' => $key,
                'label' => $definitions[$key]['label'],
                'wrap' => (bool) ($definitions[$key]['wrap'] ?? false),
            ],
            self::normalizeColumns($columns, $fallbackToCsv),
        );
    }

    public function __construct(
        private readonly ManagerDashboardService $dashboardService,
    ) {}

    /**
     * @param  list<string>|null  $columns
     * @return list<string>
     */
    public function headers(?array $columns = null, bool $fallbackToCsv = true): array
    {
        return array_column(self::visibleColumnDefs($columns, $fallbackToCsv), 'label');
    }

    /**
     * @param  array{
     *     agent_id?: ?int,
     *     lead_type?: ?string,
     *     calling_list_id?: int|string|null,
     *     dispositions?: list<string>,
     *     columns?: list<string>
     * }  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(
        int $companyId,
        array $filters,
        Carbon $start,
        Carbon $end,
        int $page = 1,
        int $perPage = self::PER_PAGE,
    ): LengthAwarePaginator {
        $definitions = DispositionDefinition::indexedForCompany($companyId);
        $leadTypeNames = $this->leadTypeNames($companyId);
        $timezone = $this->dashboardService->companyTimezone($companyId);

        return $this->historyQuery($companyId, $filters, $start, $end)
            ->paginate($perPage, ['*'], 'page', max(1, $page))
            ->through(fn (LeadHistory $row): array => $this->presentRow($row, $definitions, $leadTypeNames, $timezone));
    }

    /**
     * @param  array{
     *     agent_id?: ?int,
     *     lead_type?: ?string,
     *     calling_list_id?: int|string|null,
     *     dispositions?: list<string>,
     *     columns?: list<string>
     * }  $filters
     */
    public function count(int $companyId, array $filters, Carbon $start, Carbon $end): int
    {
        return $this->historyQuery($companyId, $filters, $start, $end)->count();
    }

    /**
     * @param  array{
     *     agent_id?: ?int,
     *     lead_type?: ?string,
     *     calling_list_id?: int|string|null,
     *     dispositions?: list<string>,
     *     columns?: list<string>
     * }  $filters
     */
    public function toCsv(int $companyId, array $filters, Carbon $start, Carbon $end): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV stream.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->headers($filters['columns'] ?? null, $this->csvColumnFallback($filters)), ',', '"', '');

        foreach ($this->csvRows($companyId, $filters, $start, $end) as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    /**
     * @param  array{
     *     agent_id?: ?int,
     *     lead_type?: ?string,
     *     calling_list_id?: int|string|null,
     *     dispositions?: list<string>,
     *     columns?: list<string>
     * }  $filters
     * @return \Generator<int, list<string>>
     */
    public function csvRows(int $companyId, array $filters, Carbon $start, Carbon $end): \Generator
    {
        $definitions = DispositionDefinition::indexedForCompany($companyId);
        $leadTypeNames = $this->leadTypeNames($companyId);
        $timezone = $this->dashboardService->companyTimezone($companyId);
        $columnKeys = array_column(
            self::visibleColumnDefs($filters['columns'] ?? null, $this->csvColumnFallback($filters)),
            'key',
        );

        foreach ($this->historyQuery($companyId, $filters, $start, $end)->lazy(500) as $row) {
            $presented = $this->presentRow($row, $definitions, $leadTypeNames, $timezone);

            yield array_map(
                fn (string $key): string => (string) ($presented[$key] ?? ''),
                $columnKeys,
            );
        }
    }

    /**
     * @param  array{
     *     agent_id?: ?int,
     *     lead_type?: ?string,
     *     calling_list_id?: int|string|null,
     *     dispositions?: list<string>,
     *     columns?: list<string>
     * }  $filters
     * @return Builder<LeadHistory>
     */
    public function historyQuery(int $companyId, array $filters, Carbon $start, Carbon $end): Builder
    {
        $query = $this->dashboardService->historyQuery(
            $companyId,
            $this->nullableInt($filters['agent_id'] ?? null),
            $this->nullableString($filters['lead_type'] ?? null),
            $start,
            $end,
            $filters['calling_list_id'] ?? null,
        )
            ->with([
                'actor' => fn ($actor) => $actor->withoutGlobalScopes(),
                'lead' => function ($lead): void {
                    $lead->withoutGlobalScopes()->with([
                        'callingList' => fn ($list) => $list->withoutGlobalScopes(),
                    ]);
                },
            ])
            ->orderBy('occurred_at')
            ->orderBy('id');

        $this->constrainDispositions($query, $this->dispositionSlugs($filters['dispositions'] ?? []));

        return $query;
    }

    /**
     * @param  Collection<string, DispositionDefinition>  $definitions
     * @param  array<string, string>  $leadTypeNames
     * @return array{
     *     lead_id: ?int,
     *     called_at: string,
     *     rep: string,
     *     disposition: string,
     *     reason: string,
     *     note: string,
     *     callback_at: string,
     *     calling_list: string,
     *     lead_type: string,
     *     first_name: string,
     *     last_name: string,
     *     name: string,
     *     phone: string,
     *     phone_2: string,
     *     email: string,
     *     city: string,
     *     state: string,
     *     zip: string,
     *     venue: string,
     *     event: string,
     *     partner_list: string,
     *     age_range: string,
     *     annual_income: string,
     *     marital_status: string,
     *     gender: string,
     *     home_owner: string,
     *     soft_score: string,
     *     qualified_partners: string,
     *     external_lead_id: string,
     *     booking_id: string,
     *     status: string,
     *     attempt_count: string,
     * }
     */
    private function presentRow(LeadHistory $row, Collection $definitions, array $leadTypeNames, string $timezone): array
    {
        $lead = $row->lead;
        $payload = $row->payload ?? [];
        $slug = $this->dispositionSlug($row);
        $definition = $slug !== '' ? $definitions->get($slug) : null;
        $leadTypeSlug = trim((string) ($lead?->lead_type ?? ''));
        $firstName = (string) ($lead?->first_name ?? '');
        $lastName = (string) ($lead?->last_name ?? '');
        $partners = $lead?->qualifiedPartnerNames() ?? [];

        return [
            'lead_id' => $lead?->id,
            'called_at' => CompanyTimezone::format($row->occurred_at, $timezone, 'Y-m-d H:i') ?? '',
            'rep' => $row->actor?->name ?? '',
            'disposition' => $definition?->label
                ?? ($slug !== '' ? $slug : ($row->event_type === LeadHistoryType::Skip ? 'Skip' : '')),
            'reason' => $this->payloadString($payload, ['reason', 'skip_reason']),
            'note' => $this->payloadString($payload, ['note']),
            'callback_at' => $this->callbackAt($payload, $row->company_id),
            'calling_list' => $lead?->callingList?->name ?? ($lead !== null ? 'Holding' : ''),
            'lead_type' => $leadTypeSlug !== ''
                ? ($leadTypeNames[$leadTypeSlug] ?? $leadTypeSlug)
                : '',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName.' '.$lastName),
            'phone' => $this->phone((string) ($lead?->phone ?? '')),
            'phone_2' => $this->phone((string) ($lead?->phone_2 ?? '')),
            'email' => (string) ($lead?->email ?? ''),
            'city' => (string) ($lead?->city ?? ''),
            'state' => (string) ($lead?->state ?? ''),
            'zip' => (string) ($lead?->zip ?? ''),
            'venue' => (string) ($lead?->venue ?? ''),
            'event' => (string) ($lead?->event ?? ''),
            'partner_list' => (string) ($lead?->partner_list ?? ''),
            'age_range' => (string) ($lead?->age_range ?? ''),
            'annual_income' => (string) ($lead?->annual_income ?? ''),
            'marital_status' => (string) ($lead?->marital_status ?? ''),
            'gender' => (string) ($lead?->gender ?? ''),
            'home_owner' => (string) ($lead?->home_owner ?? ''),
            'soft_score' => (string) ($lead?->soft_score_code ?? ''),
            'qualified_partners' => implode(', ', $partners),
            'qualification_status' => $lead?->qualification_status?->label() ?? '',
            'dnc_status' => $lead?->dnc_status?->label() ?? '',
            'rnd_status' => $lead?->rnd_status?->label() ?? '',
            'address' => (string) ($lead?->address ?? ''),
            'address_2' => (string) ($lead?->address_2 ?? ''),
            'first_name_2' => (string) ($lead?->first_name_2 ?? ''),
            'last_name_2' => (string) ($lead?->last_name_2 ?? ''),
            'original_lead_submit_date' => (string) ($lead?->original_lead_submit_date ?? ''),
            'tour_location' => (string) ($lead?->tour_location ?? ''),
            'tour_date_start' => (string) ($lead?->tour_date_start ?? ''),
            'tour_date' => (string) ($lead?->tour_date ?? ''),
            'premiums' => (string) ($lead?->premiums ?? ''),
            'tour_result' => (string) ($lead?->tour_result ?? ''),
            'tour_or_no_show' => (string) ($lead?->tour_or_no_show ?? ''),
            'file_name' => (string) ($lead?->file_name ?? ''),
            'external_lead_id' => (string) ($lead?->external_lead_id ?? ''),
            'booking_id' => (string) ($lead?->booking_id ?? ''),
            'status' => $lead?->status?->label() ?? '',
            'attempt_count' => $lead !== null ? (string) $lead->attempt_count : '',
        ];
    }

    /**
     * @param  Builder<LeadHistory>  $query
     * @param  list<string>  $slugs
     */
    private function constrainDispositions(Builder $query, array $slugs): void
    {
        if ($slugs === []) {
            return;
        }

        $includeSkip = in_array(Disposition::Skip->value, $slugs, true);
        $dispositionSlugs = array_values(array_filter(
            $slugs,
            fn (string $slug): bool => $slug !== Disposition::Skip->value,
        ));

        $query->where(function (Builder $group) use ($includeSkip, $dispositionSlugs): void {
            if ($includeSkip) {
                $group->orWhere('event_type', LeadHistoryType::Skip->value);
            }

            if ($dispositionSlugs !== []) {
                $group->orWhere(function (Builder $dispositions) use ($dispositionSlugs): void {
                    $dispositions
                        ->where('event_type', LeadHistoryType::Disposition->value)
                        ->whereIn('payload->disposition', $dispositionSlugs);
                });
            }
        });
    }

    /**
     * @return list<string>
     */
    private function dispositionSlugs(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn (mixed $slug): string => is_string($slug) ? trim($slug) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function dispositionSlug(LeadHistory $row): string
    {
        if ($row->event_type === LeadHistoryType::Skip) {
            return Disposition::Skip->value;
        }

        $slug = $row->payload['disposition'] ?? '';

        return is_string($slug) ? $slug : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function payloadString(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function callbackAt(array $payload, int $companyId): string
    {
        $value = $payload['callback_at'] ?? null;

        if (! is_string($value) || $value === '') {
            return '';
        }

        return CompanyTimezone::display($value, $companyId, 'Y-m-d H:i') ?? $value;
    }

    private function phone(string $phone): string
    {
        if ($phone === '') {
            return '';
        }

        return PhoneNormalizer::formatForDisplay($phone) ?? $phone;
    }

    /**
     * @return array<string, string>
     */
    private function leadTypeNames(int $companyId): array
    {
        return LeadTypeDefinition::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->pluck('name', 'slug')
            ->all();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function csvColumnFallback(array $filters): bool
    {
        $columns = $filters['columns'] ?? null;

        return ! is_array($columns) || $columns === [];
    }
}
