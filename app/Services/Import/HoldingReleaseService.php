<?php

namespace App\Services\Import;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\DncStatus;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
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
            ->where('company_id', $companyId);

        $this->applySourceScope($query, $filter->sourceCallingListId);

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

        if ($filter->qualificationStatus) {
            $query->where('qualification_status', $filter->qualificationStatus);
        }

        if ($filter->attemptCount !== null) {
            $query->where('attempt_count', $filter->attemptCount);
        }

        $this->applyLastDispositionFilter($query, $filter->lastDispositions);
        $this->applyAssignableScopes($query);

        return $query;
    }

    /**
     * @return array<string, string>
     */
    public function distinctHoldingColumn(
        int $companyId,
        ?string $leadType,
        string $column,
        ?int $sourceCallingListId = null,
    ): array {
        if (! in_array($column, self::DISTINCT_COLUMNS, true)) {
            throw new InvalidArgumentException("Unsupported holding filter column: {$column}");
        }

        $values = $this->assignableBaseQuery($companyId, $leadType, $sourceCallingListId)
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
    public function distinctHoldingPartners(
        int $companyId,
        ?string $leadType,
        ?int $sourceCallingListId = null,
    ): array {
        $partnerLists = $this->assignableBaseQuery($companyId, $leadType, $sourceCallingListId)
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

    /**
     * Leads that would be assigned for the current filters.
     * When $maxCount is set, only the freshest N leads are included (same as releaseFresh).
     *
     * @return Builder<Lead>
     */
    public function queryMatchingLeads(int $companyId, HoldingFilter $filter, ?int $maxCount = null): Builder
    {
        $query = $this->queryHolding($companyId, $filter);

        if ($maxCount === null) {
            return $query;
        }

        $ids = (clone $query)
            ->orderByDesc('imported_at')
            ->limit($maxCount)
            ->pluck('id');

        return Lead::withoutGlobalScopes()->whereIn('id', $ids);
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

    private function assignableBaseQuery(int $companyId, ?string $leadType, ?int $sourceCallingListId): Builder
    {
        $query = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId);

        $this->applySourceScope($query, $sourceCallingListId);

        if ($leadType) {
            $query->where('lead_type', $leadType);
        }

        return $query;
    }

    private function applySourceScope(Builder $query, ?int $sourceCallingListId): void
    {
        if ($sourceCallingListId === null) {
            $query->where('status', LeadStatus::Holding);

            return;
        }

        $query
            ->where('calling_list_id', $sourceCallingListId)
            ->whereIn('status', [LeadStatus::Callable, LeadStatus::Callback]);
    }

    /**
     * @param  list<string>|null  $lastDispositions
     */
    private function applyLastDispositionFilter(Builder $query, ?array $lastDispositions): void
    {
        if ($lastDispositions === null || $lastDispositions === []) {
            return;
        }

        $hasNone = in_array('none', $lastDispositions, true);
        $dispositions = array_values(array_filter(
            $lastDispositions,
            static fn (string $item): bool => $item !== 'none',
        ));

        $query->where(function (Builder $group) use ($hasNone, $dispositions): void {
            if ($hasNone) {
                $group->orWhereDoesntHave('history', function (Builder $history): void {
                    $history->where('event_type', LeadHistoryType::Disposition->value);
                });
            }

            if ($dispositions !== []) {
                $group->orWhereHas('latestDisposition', function (Builder $latest) use ($dispositions): void {
                    $latest->whereIn('payload->disposition', $dispositions);
                });
            }
        });
    }

    private function release(
        int $companyId,
        HoldingFilter $filter,
        int $callingListId,
        ?int $freshCount,
        ?int $actorId,
    ): int {
        if ($filter->sourceCallingListId !== null && $filter->sourceCallingListId === $callingListId) {
            throw HoldingReleaseException::sameSourceAndTarget();
        }

        $callingList = CallingList::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($callingListId);

        $sourceList = null;

        if ($filter->sourceCallingListId !== null) {
            $sourceList = CallingList::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->findOrFail($filter->sourceCallingListId);

            if ($filter->leadType && $sourceList->lead_type !== $filter->leadType) {
                throw HoldingReleaseException::leadTypeMismatch();
            }
        }

        if ($filter->leadType && $callingList->lead_type !== $filter->leadType) {
            throw HoldingReleaseException::leadTypeMismatch();
        }

        $isListToList = $filter->sourceCallingListId !== null;

        return DB::transaction(function () use (
            $companyId,
            $filter,
            $callingList,
            $sourceList,
            $freshCount,
            $actorId,
            $isListToList,
        ): int {
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

                if ($isListToList) {
                    $lead->update([
                        'calling_list_id' => $callingList->id,
                        'queue_rank' => $maxRank,
                    ]);

                    LeadHistory::withoutGlobalScopes()->create([
                        'company_id' => $companyId,
                        'lead_id' => $lead->id,
                        'actor_id' => $actorId,
                        'event_type' => LeadHistoryType::Assign,
                        'occurred_at' => $now,
                        'payload' => [
                            'from_calling_list_id' => $sourceList?->id,
                            'from_calling_list_name' => $sourceList?->name,
                            'to_calling_list_id' => $callingList->id,
                            'to_calling_list_name' => $callingList->name,
                        ],
                    ]);
                } else {
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
                }

                $released++;
            }

            return $released;
        });
    }

    private function applyAssignableScopes(Builder $query): void
    {
        $this->applyRndAssignableScope($query);
        $this->applySoftScoreAssignableScope($query);
        $this->applyQualificationAssignableScope($query);
        $this->applyDncAssignableScope($query);
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
        return $this->isRndAssignable($lead)
            && $this->isSoftScoreAssignable($lead)
            && $this->isQualificationAssignable($lead)
            && $this->isDncAssignable($lead);
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

    private function applyQualificationAssignableScope(Builder $query): void
    {
        $query->where(function (Builder $assignable): void {
            $assignable
                ->whereNull('qualification_status')
                ->orWhere('qualification_status', '!=', QualificationStatus::Pending->value);
        });
    }

    private function isQualificationAssignable(Lead $lead): bool
    {
        if ($lead->qualification_status === null) {
            return true;
        }

        return $lead->qualification_status->isAssignable();
    }

    private function applyDncAssignableScope(Builder $query): void
    {
        $query->where(function (Builder $assignable): void {
            $assignable
                ->whereNull('dnc_status')
                ->orWhere('dnc_status', DncStatus::Clear->value);
        });
    }

    private function isDncAssignable(Lead $lead): bool
    {
        if ($lead->dnc_status === null) {
            return true;
        }

        return $lead->dnc_status->isAssignable();
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
