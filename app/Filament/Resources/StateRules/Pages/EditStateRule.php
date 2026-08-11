<?php

namespace App\Filament\Resources\StateRules\Pages;

use App\Filament\Resources\StateRules\StateRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStateRule extends EditRecord
{
    protected static string $resource = StateRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
