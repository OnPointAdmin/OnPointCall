<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Enums\ImportBatchStatus;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use Filament\Resources\Pages\ViewRecord;

class ViewImportBatch extends ViewRecord
{
    protected static string $resource = ImportBatchResource::class;

    protected string $view = 'filament.resources.import-batches.view-import-batch';

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function refreshRecord(): void
    {
        $this->record = $this->getRecord()->refresh();
        $this->fillForm();
    }

    public function isProcessing(): bool
    {
        return in_array($this->getRecord()->status, [
            ImportBatchStatus::Pending,
            ImportBatchStatus::Processing,
        ], true);
    }
}
