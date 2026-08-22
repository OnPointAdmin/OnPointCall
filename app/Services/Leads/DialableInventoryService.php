<?php

namespace App\Services\Leads;

use App\DataTransferObjects\DialableInventory;
use App\Enums\LeadStatus;
use App\Models\CallingList;
use App\Models\Lead;
use App\Services\Compliance\ComplianceService;

class DialableInventoryService
{
    /** @var array<int, array<int, DialableInventory>> */
    private array $byCompany = [];

    public function __construct(
        private readonly ComplianceService $compliance,
    ) {}

    public function forList(CallingList $list): DialableInventory
    {
        return $this->forCompany((int) $list->company_id)[$list->id] ?? DialableInventory::empty();
    }

    /**
     * @return array<int, DialableInventory>
     */
    public function forCompany(int $companyId): array
    {
        return $this->byCompany[$companyId] ??= $this->compute($companyId);
    }

    /**
     * @return array<int, DialableInventory>
     */
    private function compute(int $companyId): array
    {
        $leads = Lead::withoutGlobalScopes()
            ->with([
                'callingList.cadence.dayParts',
                'callingList.cadence.attemptGaps',
                'claim',
            ])
            ->where('company_id', $companyId)
            ->where('status', LeadStatus::Callable)
            ->get();

        $ready = [];
        $waiting = [];

        foreach ($leads as $lead) {
            $listId = (int) $lead->calling_list_id;

            if ($listId === 0 || ! $this->compliance->hasAttemptsRemaining($lead)) {
                continue;
            }

            $claimed = $lead->claim !== null && $lead->claim->expires_at?->isFuture();

            if (! $claimed && $this->compliance->canDialNow($lead)) {
                $ready[$listId] = ($ready[$listId] ?? 0) + 1;

                continue;
            }

            $waiting[$listId] = ($waiting[$listId] ?? 0) + 1;
        }

        $counts = [];

        foreach (array_unique([...array_keys($ready), ...array_keys($waiting)]) as $listId) {
            $counts[$listId] = new DialableInventory(
                readyNow: $ready[$listId] ?? 0,
                waiting: $waiting[$listId] ?? 0,
            );
        }

        return $counts;
    }
}
