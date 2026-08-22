<?php

namespace Tests\Support;

use App\Models\Cadence;
use App\Models\CallingList;
use App\Support\CadenceDefaults;
use App\Support\CadenceProvisioner;

trait CreatesCadences
{
    protected function createCadence(
        int $companyId,
        string $name = 'Standard',
        ?array $dayParts = null,
        ?array $attemptGaps = null,
        bool $prioritizeUnattempted = true,
    ): Cadence {
        return CadenceProvisioner::create(
            companyId: $companyId,
            name: $name,
            prioritizeUnattempted: $prioritizeUnattempted,
            dayParts: $dayParts,
            attemptGaps: $attemptGaps,
        );
    }

    protected function createCallingList(
        int $companyId,
        ?Cadence $cadence = null,
        array $overrides = [],
    ): CallingList {
        $cadence ??= $this->createCadence($companyId);

        return CallingList::withoutGlobalScopes()->create(array_merge([
            'company_id' => $companyId,
            'name' => 'Test List',
            'lead_type' => 'standard',
            'cadence_id' => $cadence->id,
            'active' => true,
        ], $overrides));
    }

    /**
     * @param  list<string>|null  $dayParts
     */
    protected function createCadenceWithDayParts(
        int $companyId,
        ?array $dayParts = null,
        ?array $attemptGaps = null,
    ): Cadence {
        return $this->createCadence(
            companyId: $companyId,
            dayParts: CadenceDefaults::dayPartRows($dayParts),
            attemptGaps: $attemptGaps ?? [['after_attempt' => 1, 'wait_value' => 60, 'wait_unit' => 'minutes']],
        );
    }
}
