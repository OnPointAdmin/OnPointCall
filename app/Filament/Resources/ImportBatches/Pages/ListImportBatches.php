<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Filament\Pages\ImportLeads;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListImportBatches extends ListRecords
{
    protected static string $resource = ImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import CSV')
                ->url(ImportLeads::getUrl()),
        ];
    }
}
