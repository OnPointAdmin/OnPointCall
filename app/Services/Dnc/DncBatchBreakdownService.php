<?php

namespace App\Services\Dnc;

use App\Enums\DncStatus;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\QualifyBatch;
use App\Support\DncBatchBreakdown;
use Illuminate\Support\Collection;

class DncBatchBreakdownService
{
    public function forImportBatch(ImportBatch $batch): DncBatchBreakdown
    {
        $leads = $batch->leads()
            ->whereNotNull('dnc_status')
            ->where('dnc_status', '!=', DncStatus::Pending->value)
            ->get(['id', 'dnc_status', 'dnc_result']);

        return $this->fromLeads($leads);
    }

    public function forQualifyBatch(QualifyBatch $batch): DncBatchBreakdown
    {
        $leads = $batch->leads()
            ->whereNotNull('dnc_status')
            ->where('dnc_status', '!=', DncStatus::Pending->value)
            ->get(['leads.id', 'dnc_status', 'dnc_result']);

        return $this->fromLeads($leads);
    }

    /**
     * @param  Collection<int, Lead>  $leads
     */
    private function fromLeads(Collection $leads): DncBatchBreakdown
    {
        $counts = [
            'litigator' => 0,
            'internal' => 0,
            'national' => 0,
            'state' => 0,
            'dnc' => 0,
            'nationalIgnored' => 0,
            'stateIgnored' => 0,
        ];

        foreach ($leads as $lead) {
            $result = $lead->dnc_result;

            if (! is_array($result)) {
                continue;
            }

            if ($lead->dnc_status === DncStatus::Hit) {
                $reason = (string) ($result['hit_reason'] ?? 'dnc');

                $counts[match ($reason) {
                    'litigator' => 'litigator',
                    'idnc' => 'internal',
                    'national' => 'national',
                    'state' => 'state',
                    default => 'dnc',
                }]++;
            }

            if ($lead->dnc_status !== DncStatus::Clear) {
                continue;
            }

            $ignoredReasons = $result['ignored_reasons'] ?? [];

            if (! is_array($ignoredReasons)) {
                continue;
            }

            if (in_array('national', $ignoredReasons, true)) {
                $counts['nationalIgnored']++;
            }

            if (in_array('state', $ignoredReasons, true)) {
                $counts['stateIgnored']++;
            }
        }

        return new DncBatchBreakdown(
            litigator: $counts['litigator'],
            internal: $counts['internal'],
            national: $counts['national'],
            state: $counts['state'],
            dnc: $counts['dnc'],
            nationalIgnored: $counts['nationalIgnored'],
            stateIgnored: $counts['stateIgnored'],
        );
    }
}
