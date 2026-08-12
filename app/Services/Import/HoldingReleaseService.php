<?php

namespace App\Services\Import;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
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
        'soft_score_status',
        'soft_score_code',
        'age_range',
        'annual_income',
        'marital_status',
        'gender',
        'home_owner',
        'tour_location',
        'tour_date',
    ];

    public function queryHolding(int $companyId, HoldingFilter $filter): Builder
    {
        $query = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Holding);

        if ($filter->leadType) {
            $query->where('lead_type', $filter->leadType);
        }

        if ($filter->state) {
            $query->where('state', strtoupper($filter->state));
        }

        if ($filter->venue) {
            $query->where('venue', $filter->venue);
        }

        if ($filter->event) {
            $query->where('event', $filter->event);
        }

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

        if ($filter->partner) {
            $query->where('partner_list', 'ilike', '%'.$filter->partner.'%');
        }

        if ($filter->fileName) {
            $query->where('file_name', 'ilike', '%'.$filter->fileName.'%');
        }

        if ($filter->softScoreStatus) {
            $query->where('soft_score_status', $filter->softScoreStatus);
        }

        if ($filter->softScoreCode) {
            $query->where('soft_score_code', $filter->softScoreCode);
        }

        if ($filter->ageRange) {
            $query->where('age_range', $filter->ageRange);
        }

        if ($filter->annualIncome) {
            $query->where('annual_income', $filter->annualIncome);
        }

        if ($filter->maritalStatus) {
            $query->where('marital_status', $filter->maritalStatus);
        }

        if ($filter->gender) {
            $query->where('gender', $filter->gender);
        }

        if ($filter->homeOwner) {
            $query->where('home_owner', $filter->homeOwner);
        }

        if ($filter->tourLocation) {
            $query->where('tour_location', $filter->tourLocation);
        }

        if ($filter->tourDate) {
            $query->where('tour_date', $filter->tourDate);
        }

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
            foreach (explode(',', (string) $partnerList) as $partner) {
                $partner = trim($partner);

                if ($partner !== '') {
                    $partners[$partner] = $partner;
                }
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
}
