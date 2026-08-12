<?php

namespace App\Filament\Resources\ImportMappings\Pages;

use App\Filament\Resources\ImportMappings\Concerns\ManagesImportMappingFormData;
use App\Filament\Resources\ImportMappings\ImportMappingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImportMapping extends EditRecord
{
    use ManagesImportMappingFormData;

    protected static string $resource = ImportMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
