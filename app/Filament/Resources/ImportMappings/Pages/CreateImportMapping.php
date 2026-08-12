<?php

namespace App\Filament\Resources\ImportMappings\Pages;

use App\Filament\Resources\ImportMappings\Concerns\ManagesImportMappingFormData;
use App\Filament\Resources\ImportMappings\ImportMappingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImportMapping extends CreateRecord
{
    use ManagesImportMappingFormData;

    protected static string $resource = ImportMappingResource::class;
}
