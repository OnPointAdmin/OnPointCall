<?php

namespace App\Services\Import;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Exceptions\HoldingReleaseException;
use App\Models\CallingList;
use App\Models\Lead;
use App\Models\LeadHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HoldingReleaseService
{
    private const DISTINCT_COLUMNS = [
        'state',
        'venue',
        'event',
        'soft_score_code',
        'age_range',
        'annual_income',
        'marital_status',
        'gender',
        'home_owner',
        'tour_location',
        'tour_date_start',
        'tour_date',
        'tour_result',
    ];

    public function queryHolding(int $companyId, HoldingFilter $filter): Builder
    {
        $query = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Holding);

        if ($filter->leadType) {
            $query->where('lead_type', $filter->leadType);
        }

        $this->applyExactInFilter($query, 'state', $filter->state, upper: true);
        $this->applyExactInFilter($query, 'venue', $filter->venue);
        $this->applyExactInFilter($query, 'event', $filter->event);
        $this->applyExactInFilter($query, 'soft_score_code', $filter->softScoreCode);
        $this->applyExactInFilter($query, 'age_range', $filter->ageRange);
        $this->applyExactInFilter($query, 'annual_income', $filter->annualIncome);
        $this->applyExactInFilter($query, 'marital_status', $filter->maritalStatus);
        $this->applyExactInFilter($query, 'gender', $filter->gender);
        $this->applyExactInFilter($query, 'home_owner', $filter->homeOwner);
        $this->applyExactInFilter($query, 'tour_location', $filter->tourLocation);
        $this->applyExactInFilter($query, 'tour_date_start', $filter->tourDateStart);
        $this->applyExactInFilter($query, 'tour_date', $filter->tourDate);
        $this->applyExactInFilter($query, 'tour_result', $filter->tourResult);

        if ($filter->importBatchId) {
            $query->where('import_batch_id', $filter->importBatchId);
        }

        if ($filter->importedFrom) {
            $query->where('imported_at', '>=', $filter->importedFrom);
        }

        if ($filter->importedTo) {
            $query->where('imported_at', '<=', $filter->importedTo);
        }

        if ($filter->zip) {
            $query->where('zip', 'like', substr($filter->zip, 0, 5).'%');
        }

        $partners = $this->selectedValues($filter->partner);

        if ($partners !== []) {
            $query->where(function (Builder $partnerQuery) use ($partners): void {
                foreach ($partners as $partner) {
                    $partnerQuery->orWhere('partner_list', 'ilike', '%'.$partner.'%');
                }
            });
        }

        if ($filter->fileName) {
            $query->where('file_name', 'ilike', '%'.$filter->fileName.'%');
        }

        $this->applyAssignableScopes($query);

        return $query;
    }

    /**
     * @return array<string, string>
     */
    public function distinctHoldingColumn(int $companyId, ?string $leadType, string $column): array
    {
        if (! in_array($column, self::DISTINCT_COLUMNS, true)) {
            throw new InvalidArgumentException("Unsupported holding filter column: {$column}");
        }

        $values = $this->holdingBaseQuery($companyId, $leadType)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        $options = [];

        foreach ($values as $value) {
            $options[(string) $value] = (string) $value;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function distinctHoldingPartners(int $companyId, ?string $leadType): array
    {
        $partnerLists = $this->holdingBaseQuery($companyId, $leadType)
            ->whereNotNull('partner_list')
            ->where('partner_list', '!=', '')
            ->pluck('partner_list');

        $partners = [];

        foreach ($partnerLists as $partnerList) {
            foreach ($this->splitPartnerList((string) $partnerList) as $partner) {
                $partners[$partner] = $partner;
            }
        }

        ksort($partners);

        return $partners;
    }

    public function countHolding(int $companyId, HoldingFilter $filter): int
    {
        return $this->queryHolding($companyId, $filter)->count();
    }

    public function releaseAll(
        int $companyId,
        HoldingFilter $filter,
        int $callingListId,
        ?int $actorId = null,
    ): int {
        return $this->release($companyId, $filter, $callingListId, null, $actorId);
    }

    public function releaseFresh(
        int $companyId,
        HoldingFilter $filter,
        int $callingListId,
        int $count,
        ?int $actorId = null,
    ): int {
        if ($count < 1) {
            throw HoldingReleaseException::invalidCount();
        }

        return $this->release($companyId, $filter, $callingListId, $count, $actorId);
    }

    private function holdingBaseQuery(int $companyId, ?string $leadType): Builder
    {
        $query = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Holding);

        if ($leadType) {
            $query->where('lead_type', $leadType);
        }

        return $query;
    }

    private function release(
        int $companyId,
        HoldingFilter $filter,
        int $callingListId,
        ?int $freshCount,
        ?int $actorId,
    ): int {
        $callingList = CallingList::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($callingListId);

        if ($filter->leadType && $callingList->lead_type !== $filter->leadType) {
            throw HoldingReleaseException::leadTypeMismatch();
        }

        return DB::transaction(function () use ($companyId, $filter, $callingList, $freshCount, $actorId): int {
            $query = $this->queryHolding($companyId, $filter)
                ->lockForUpdate();

            if ($filter->leadType === null) {
                $query->where('lead_type', $callingList->lead_type);
            } elseif ($callingList->lead_type !== $filter->leadType) {
                throw HoldingReleaseException::leadTypeMismatch();
            }

            if ($freshCount !== null) {
                $leads = $query->orderByDesc('imported_at')->limit($freshCount)->get();
            } else {
                $leads = $query->orderBy('imported_at')->get();
            }

            if ($leads->isEmpty()) {
                return 0;
            }

            $maxRank = (int) Lead::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('calling_list_id', $callingList->id)
                ->max('queue_rank');

            $released = 0;
            $now = now();

            foreach ($leads as $lead) {
                if ($lead->lead_type !== $callingList->lead_type) {
                    throw HoldingReleaseException::leadTypeMismatch();
                }

                if (! $this->isAssignable($lead)) {
                    continue;
                }

                $maxRank++;

                $lead->update([
                    'calling_list_id' => $callingList->id,
                    'status' => LeadStatus::Callable,
                    'lead_type' => $callingList->lead_type,
                    'queue_rank' => $maxRank,
                ]);

                LeadHistory::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'lead_id' => $lead->id,
                    'actor_id' => $actorId,
                    'event_type' => LeadHistoryType::Release,
                    'occurred_at' => $now,
                    'payload' => [
                        'calling_list_id' => $callingList->id,
                        'calling_list_name' => $callingList->name,
                    ],
                ]);

                $released++;
            }

            return $released;
        });
    }

    private function applyAssignableScopes(Builder $query): void
    {
        $this->applyRndAssignableScope($query);
        $this->applySoftScoreAssignableScope($query);
    }

    private function applyRndAssignableScope(Builder $query): void
    {
        $query->where(function (Builder $assignable): void {
            $assignable
                ->whereNull('rnd_status')
                ->orWhereIn('rnd_status', [
                    RndStatus::Clear->value,
                    RndStatus::NoData->value,
                ]);
        });
    }

    private function applySoftScoreAssignableScope(Builder $query): void
    {
        $query->where(function (Builder $assignable): void {
            $assignable
                ->whereNull('soft_score_status')
                ->orWhere('soft_score_status', '!=', SoftScoreStatus::Pending->value);
        });
    }

    private function isAssignable(Lead $lead): bool
    {
        return $this->isRndAssignable($lead) && $this->isSoftScoreAssignable($lead);
    }

    private function isRndAssignable(Lead $lead): bool
    {
        if ($lead->rnd_status === null) {
            return true;
        }

        return $lead->rnd_status->isAssignable();
    }

    private function isSoftScoreAssignable(Lead $lead): bool
    {
        if ($lead->soft_score_status === null) {
            return true;
        }

        return $lead->soft_score_status->isAssignable();
    }

    /**
     * @param  list<string>|string|null  $value
     */
    private function applyExactInFilter(Builder $query, string $column, array|string|null $value, bool $upper = false): void
    {
        $values = $this->selectedValues($value);

        if ($values === []) {
            return;
        }

        if ($upper) {
            $values = array_map(static fn (string $item): string => strtoupper($item), $values);
        }

        $query->whereIn($column, $values);
    }

    /**
     * @param  list<string>|string|null  $value
     * @return list<string>
     */
    private function selectedValues(array|string|null $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $values),
            static fn (string $item): bool => $item !== '',
        ));
    }

    /**
     * Split a comma-separated partner list, trimming each name and keeping
     * commas that precede "LLC" as part of the partner name.
     *
     * @return list<string>
     */
    private function splitPartnerList(string $partnerList): array
    {
        $parts = preg_split('/,(?!\s*LLC\b)/i', trim($partnerList)) ?: [];

        $partners = [];

        foreach ($parts as $partner) {
            $partner = trim(preg_replace('/^and\s+/i', '', trim($partner)) ?? '');

            if ($partner !== '') {
                $partners[] = $partner;
            }
        }

        return $partners;
    }
}
