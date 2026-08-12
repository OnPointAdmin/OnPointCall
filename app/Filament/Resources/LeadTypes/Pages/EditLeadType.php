<?php

namespace App\Filament\Resources\LeadTypes\Pages;

use App\Filament\Resources\LeadTypes\LeadTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLeadType extends EditRecord
{
    protected static string $resource = LeadTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
