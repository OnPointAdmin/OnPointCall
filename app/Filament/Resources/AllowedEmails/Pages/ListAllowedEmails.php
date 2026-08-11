<?php

namespace App\Filament\Resources\AllowedEmails\Pages;

use App\Filament\Resources\AllowedEmails\AllowedEmailResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAllowedEmails extends ListRecords
{
    protected static string $resource = AllowedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
