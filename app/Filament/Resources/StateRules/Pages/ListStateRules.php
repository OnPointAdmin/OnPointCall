<?php

namespace App\Filament\Resources\StateRules\Pages;

use App\Filament\Resources\StateRules\StateRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStateRules extends ListRecords
{
    protected static string $resource = StateRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
