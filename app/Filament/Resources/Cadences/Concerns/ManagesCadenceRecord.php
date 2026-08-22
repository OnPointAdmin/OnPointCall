<?php

namespace App\Filament\Resources\Cadences\Concerns;

use App\Models\Cadence;
use App\Support\CadenceProvisioner;
use App\Support\CadenceValidator;

trait ManagesCadenceRecord
{
    /** @var array<string, mixed>|null */
    protected ?array $cadenceSnapshotBeforeSave = null;

    protected function afterCreate(): void
    {
        CadenceProvisioner::ensureChildren($this->record);
        $this->recordSettingsHistory([], CadenceProvisioner::snapshot($this->record));
    }

    protected function beforeSave(): void
    {
        if ($this->record instanceof Cadence && $this->record->exists) {
            $this->cadenceSnapshotBeforeSave = CadenceProvisioner::snapshot($this->record);
        }
    }

    protected function afterSave(): void
    {
        $this->record->load(['dayParts', 'attemptGaps']);

        if ($this->cadenceSnapshotBeforeSave !== null) {
            $this->recordSettingsHistory(
                $this->cadenceSnapshotBeforeSave,
                CadenceProvisioner::snapshot($this->record),
            );
            $this->cadenceSnapshotBeforeSave = null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['dayParts'])) {
            CadenceValidator::validateDayParts($data['dayParts']);
        }

        if (! empty($data['attemptGaps'])) {
            CadenceValidator::validateAttemptGaps($data['attemptGaps']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    protected function recordSettingsHistory(array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        app(\App\Services\Settings\SettingsHistoryService::class)->record(
            $this->record,
            $before,
            $after,
            'cadences:'.$this->record->getKey(),
        );
    }
}
