<?php

namespace App\Filament\Resources\SettingsHistories\Pages;

use App\Filament\Resources\SettingsHistories\SettingsHistoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSettingsHistory extends EditRecord
{
    protected static string $resource = SettingsHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
