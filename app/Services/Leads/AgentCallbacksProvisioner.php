<?php

namespace App\Services\Leads;

use App\Models\BookingCallbackSchedule;
use App\Models\Cadence;
use App\Models\CallingList;
use App\Support\CadenceProvisioner;

class AgentCallbacksProvisioner
{
    public const LIST_NAME = 'Agent Callbacks';

    public function ensure(int $companyId): CallingList
    {
        $list = $this->listFor($companyId);
        $this->scheduleFor($companyId);

        return $list;
    }

    public function listFor(int $companyId): CallingList
    {
        $existing = CallingList::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('name', self::LIST_NAME)
            ->first();

        if ($existing) {
            return $existing;
        }

        $standard = CallingList::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('name', 'Standard')
            ->first();

        $cadenceId = $standard?->cadence_id ?? $this->standardCadenceId($companyId);

        return CallingList::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'name' => self::LIST_NAME,
            'lead_type' => 'standard',
            'cadence_id' => $cadenceId,
            'active' => true,
            'booking_url_template' => $standard?->booking_url_template,
            'booking_param_map' => $standard?->booking_param_map ?: null,
        ]);
    }

    public function scheduleFor(int $companyId): BookingCallbackSchedule
    {
        $list = $this->listFor($companyId);

        return BookingCallbackSchedule::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'calling_list_id' => $list->id,
                'enabled' => true,
                'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                'run_times' => ['07:00'],
            ],
        );
    }

    public static function isList(CallingList $list): bool
    {
        return $list->name === self::LIST_NAME;
    }

    private function standardCadenceId(int $companyId): int
    {
        $cadence = Cadence::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('name', 'Standard')
            ->first();

        if (! $cadence) {
            $cadence = CadenceProvisioner::create($companyId, 'Standard');
        }

        return $cadence->id;
    }
}
