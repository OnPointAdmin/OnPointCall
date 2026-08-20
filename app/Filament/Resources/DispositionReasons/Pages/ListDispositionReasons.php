<?php

namespace App\Filament\Resources\DispositionReasons\Pages;

use App\Filament\Resources\DispositionReasons\DispositionReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDispositionReasons extends ListRecords
{
    protected static string $resource = DispositionReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
