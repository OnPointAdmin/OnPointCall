<?php

namespace App\Filament\Resources\BlackoutDates\Pages;

use App\Filament\Resources\BlackoutDates\BlackoutDateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlackoutDates extends ListRecords
{
    protected static string $resource = BlackoutDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
