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

class HoldingReleaseService
{
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
            $query->where('venue', 'ilike', '%'.$filter->venue.'%');
        }

        if ($filter->event) {
            $query->where('event', 'ilike', '%'.$filter->event.'%');
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

        if ($filter->softScoreStatus) {
            $query->where('soft_score_status', $filter->softScoreStatus);
        }

        if ($filter->softScoreCode) {
            $query->where('soft_score_code', $filter->softScoreCode);
        }

        return $query;
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
