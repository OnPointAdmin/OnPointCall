<?php

namespace App\Services\Compliance;

use App\Enums\LeadStatus;
use App\Models\AppSetting;
use App\Models\BlackoutDate;
use App\Models\Lead;
use App\Models\StateRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ComplianceService
{
    public function __construct(
        private readonly DayPartResolver $dayPartResolver,
    ) {}

    /**
     * @var array<int, Collection<int, StateRule>>
     */
    private array $stateRulesCache = [];

    /**
     * @var array<int, Collection<int, BlackoutDate>>
     */
    private array $blackoutCache = [];

    /**
     * @var array<int, AppSetting|null>
     */
    private array $settingsCache = [];

    public function canDialNow(Lead $lead, ?CarbonInterface $at = null): bool
    {
        if ($lead->status === LeadStatus::Dnc) {
            return false;
        }

        if (! in_array($lead->status, [LeadStatus::Callable, LeadStatus::Callback], true)) {
            return false;
        }

        return $this->isWithinLegalWindow($lead, $at)
            && $this->isCadenceReady($lead, $at);
    }

    public function isWithinLegalWindow(Lead $lead, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if ($this->isBlackedOut($lead, $at)) {
            return false;
        }

        $rule = $this->resolveStateRule($lead);

        if (! $rule) {
            return false;
        }

        $local = $at->copy()->timezone($lead->timezone ?: 'America/New_York');
        $weekday = (int) $local->dayOfWeek;
        $permitted = $rule->permitted_weekdays ?? [];

        if ($permitted !== [] && ! in_array($weekday, $permitted, true)) {
            return false;
        }

        $time = $local->format('H:i:s');
        $start = $this->normalizeTime((string) $rule->window_start);
        $end = $this->normalizeTime((string) $rule->window_end);

        return $time >= $start && $time < $end;
    }

    public function isBlackedOut(Lead $lead, ?CarbonInterface $at = null): bool
    {
        $at ??= now();
        $local = $at->copy()->timezone($lead->timezone ?: 'America/New_York');
        $date = $local->toDateString();

        return $this->blackoutsFor($lead->company_id)
            ->contains(fn (BlackoutDate $blackout): bool => $blackout->date->toDateString() === $date);
    }

    public function isManualDialOnly(Lead $lead): bool
    {
        return (bool) $this->resolveStateRule($lead)?->manual_dial_only;
    }

    public function isCadenceReady(Lead $lead, ?CarbonInterface $at = null): bool
    {
        return $this->dayPartResolver->isCadenceGapSatisfied($lead, $at)
            && $this->dayPartResolver->matchesNextDayPart($lead, $at);
    }

    public function maxAttemptsFor(Lead $lead): int
    {
        if ($lead->callingList?->max_attempts_override) {
            return $lead->callingList->max_attempts_override;
        }

        return $this->settingsFor($lead->company_id)?->max_attempts ?? 6;
    }

    public function claimTtlMinutesFor(Lead $lead): int
    {
        return $this->settingsFor($lead->company_id)?->claim_ttl_minutes ?? 20;
    }

    public function hasAttemptsRemaining(Lead $lead): bool
    {
        return $lead->attempt_count < $this->maxAttemptsFor($lead);
    }

    public function validateCallbackTime(Lead $lead, CarbonInterface $callbackAt): bool
    {
        return $this->isWithinLegalWindow($lead, $callbackAt);
    }

    private function resolveStateRule(Lead $lead): ?StateRule
    {
        $rules = $this->stateRulesFor($lead->company_id);
        $state = strtoupper((string) $lead->state);

        if ($state !== '' && ($match = $rules->firstWhere('state_code', $state))) {
            return $match;
        }

        return $rules->firstWhere('state_code', 'DEFAULT');
    }

    /**
     * @return Collection<int, StateRule>
     */
    private function stateRulesFor(int $companyId): Collection
    {
        return $this->stateRulesCache[$companyId] ??= StateRule::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get();
    }

    /**
     * @return Collection<int, BlackoutDate>
     */
    private function blackoutsFor(int $companyId): Collection
    {
        return $this->blackoutCache[$companyId] ??= BlackoutDate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get();
    }

    private function settingsFor(int $companyId): ?AppSetting
    {
        return $this->settingsCache[$companyId] ??= AppSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
