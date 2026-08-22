<?php

namespace App\Services\Compliance;

use App\Models\CadenceAttemptGap;
use App\Models\Lead;
use App\Support\CadenceWait;
use Carbon\CarbonInterface;

class AttemptGapResolver
{
    public function isGapSatisfied(Lead $lead, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if ($lead->last_attempt_at === null) {
            return true;
        }

        $eligibleAt = $this->eligibleAt($lead);

        return $eligibleAt === null || $at->gte($eligibleAt);
    }

    public function eligibleAt(Lead $lead): ?CarbonInterface
    {
        if ($lead->last_attempt_at === null) {
            return null;
        }

        $rule = $this->matchingRule($lead);

        if ($rule === null) {
            return null;
        }

        return $this->applyWait($lead, $rule, $lead->last_attempt_at);
    }

    private function matchingRule(Lead $lead): ?CadenceAttemptGap
    {
        $lead->loadMissing('callingList.cadence.attemptGaps');

        $gaps = $lead->callingList?->cadence?->attemptGaps;

        if ($gaps === null || $gaps->isEmpty()) {
            return null;
        }

        return $gaps
            ->filter(fn (CadenceAttemptGap $gap): bool => $lead->attempt_count >= $gap->after_attempt)
            ->sortByDesc('after_attempt')
            ->first();
    }

    private function applyWait(Lead $lead, CadenceAttemptGap $rule, CarbonInterface $from): CarbonInterface
    {
        return CadenceWait::eligibleAt($lead, $rule->wait_value, $rule->wait_unit, $from);
    }
}
