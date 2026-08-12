<?php

namespace App\Filament\Resources\ImportMappings\Concerns;

use Filament\Actions\Action;

trait ManagesImportMappingFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['mapping_rows'] = collect($data['column_map'] ?? [])
            ->map(fn (mixed $source, string $destination): array => [
                'source' => is_string($source) ? $source : (string) $source,
                'destination' => $destination,
            ])
            ->values()
            ->all();

        unset($data['column_map']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->flattenImportMappingFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->flattenImportMappingFormData($data);
    }

    protected function getSaveFormAction(): Action
    {
        return $this->configureImportMappingConfirmAction(parent::getSaveFormAction());
    }

    protected function getCreateFormAction(): Action
    {
        return $this->configureImportMappingConfirmAction(parent::getCreateFormAction());
    }

    protected function configureImportMappingConfirmAction(Action $action): Action
    {
        return $action
            ->requiresConfirmation(fn (): bool => $this->hasDuplicateMappingDestinations())
            ->modalHeading('Duplicate lead field mappings')
            ->modalDescription(fn (): string => $this->getDuplicateMappingDescription());
    }

    protected function hasDuplicateMappingDestinations(): bool
    {
        return $this->getDuplicateMappingDestinations() !== [];
    }

    /**
     * @return list<string>
     */
    protected function getDuplicateMappingDestinations(): array
    {
        $rows = $this->form->getState()['mapping_rows'] ?? [];
        $seen = [];
        $duplicates = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $destination = trim((string) ($row['destination'] ?? ''));

            if ($destination === '') {
                continue;
            }

            if (isset($seen[$destination])) {
                $duplicates[$destination] = true;
            }

            $seen[$destination] = true;
        }

        return array_keys($duplicates);
    }

    protected function getDuplicateMappingDescription(): string
    {
        $fields = $this->getDuplicateMappingDestinations();

        if ($fields === []) {
            return '';
        }

        return 'These lead fields are mapped more than once: '.implode(', ', $fields).'. '
            .'Only the last mapping for each field will be used when importing. Save anyway?';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function flattenImportMappingFormData(array $data): array
    {
        $columnMap = [];

        foreach ($data['mapping_rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $source = trim((string) ($row['source'] ?? ''));
            $destination = trim((string) ($row['destination'] ?? ''));

            if ($source === '' || $destination === '') {
                continue;
            }

            $columnMap[$destination] = $source;
        }

        unset($data['mapping_rows']);
        $data['column_map'] = $columnMap;

        return $data;
    }
}
