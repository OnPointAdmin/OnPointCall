<?php

namespace App\Filament\Resources\BlackoutDates\Pages;

use App\Filament\Resources\BlackoutDates\BlackoutDateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlackoutDate extends EditRecord
{
    protected static string $resource = BlackoutDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
