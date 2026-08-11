<?php

namespace App\Filament\Resources\SettingsHistories\Pages;

use App\Filament\Resources\SettingsHistories\SettingsHistoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSettingsHistories extends ListRecords
{
    protected static string $resource = SettingsHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
