<?php

namespace App\Filament\Resources\SettingsHistories\Pages;

use App\Filament\Resources\SettingsHistories\SettingsHistoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSettingsHistory extends ViewRecord
{
    protected static string $resource = SettingsHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
