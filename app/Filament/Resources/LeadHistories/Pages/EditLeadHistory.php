<?php

namespace App\Filament\Resources\LeadHistories\Pages;

use App\Filament\Resources\LeadHistories\LeadHistoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLeadHistory extends EditRecord
{
    protected static string $resource = LeadHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
