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

    public function __construct(
        private readonly ManagerDashboardService $dashboardService,
    ) {}

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'Called At',
            'Rep',
            'Disposition',
            'Reason',
            'Note',
            'Callback At',
            'Calling List',
            'Lead Type',
            'First Name',
            'Last Name',
            'Phone',
            'Phone 2',
            'Email',
            'City',
            'State',
            'Zip',
            'Venue',
            'Event',
            'Partner List',
            'Lead ID',
            'Booking ID',
            'Current Status',
            'Attempt Count',
        ];
    }

    /**
     * @param  array{
     *     agent_id?: ?int,
     *     lead_type?: ?string,
     *     calling_list_id?: int|string|null,
     *     dispositions?: list<string>
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
     *     dispositions?: list<string>
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
     *     dispositions?: list<string>
     * }  $filters
     */
    public function toCsv(int $companyId, array $filters, Carbon $start, Carbon $end): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV stream.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->headers(), ',', '"', '');

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
     *     dispositions?: list<string>
     * }  $filters
     * @return \Generator<int, list<string>>
     */
    public function csvRows(int $companyId, array $filters, Carbon $start, Carbon $end): \Generator
    {
        $definitions = DispositionDefinition::indexedForCompany($companyId);
        $leadTypeNames = $this->leadTypeNames($companyId);
        $timezone = $this->dashboardService->companyTimezone($companyId);

        foreach ($this->historyQuery($companyId, $filters, $start, $end)->lazy(500) as $row) {
            $presented = $this->presentRow($row, $definitions, $leadTypeNames, $timezone);

            yield [
                $presented['called_at'],
                $presented['rep'],
                $presented['disposition'],
                $presented['reason'],
                $presented['note'],
                $presented['callback_at'],
                $presented['calling_list'],
                $presented['lead_type'],
                $presented['first_name'],
                $presented['last_name'],
                $presented['phone'],
                $presented['phone_2'],
                $presented['email'],
                $presented['city'],
                $presented['state'],
                $presented['zip'],
                $presented['venue'],
                $presented['event'],
                $presented['partner_list'],
                $presented['external_lead_id'],
                $presented['booking_id'],
                $presented['status'],
                $presented['attempt_count'],
            ];
        }
    }

    /**
     * @param  array{
     *     agent_id?: ?int,
     *     lead_type?: ?string,
     *     calling_list_id?: int|string|null,
     *     dispositions?: list<string>
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
     *     phone: string,
     *     phone_2: string,
     *     email: string,
     *     city: string,
     *     state: string,
     *     zip: string,
     *     venue: string,
     *     event: string,
     *     partner_list: string,
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
            'first_name' => (string) ($lead?->first_name ?? ''),
            'last_name' => (string) ($lead?->last_name ?? ''),
            'phone' => $this->phone((string) ($lead?->phone ?? '')),
            'phone_2' => $this->phone((string) ($lead?->phone_2 ?? '')),
            'email' => (string) ($lead?->email ?? ''),
            'city' => (string) ($lead?->city ?? ''),
            'state' => (string) ($lead?->state ?? ''),
            'zip' => (string) ($lead?->zip ?? ''),
            'venue' => (string) ($lead?->venue ?? ''),
            'event' => (string) ($lead?->event ?? ''),
            'partner_list' => (string) ($lead?->partner_list ?? ''),
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
}
