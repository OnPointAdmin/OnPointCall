<?php

namespace App\Filament\Resources\Dispositions\Pages;

use App\Filament\Resources\Dispositions\DispositionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDisposition extends CreateRecord
{
    protected static string $resource = DispositionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_system'] = false;

        return $data;
    }
}
