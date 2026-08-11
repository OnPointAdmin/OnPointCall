<?php

namespace App\Filament\Resources\LeadHistories\Pages;

use App\Filament\Resources\LeadHistories\LeadHistoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLeadHistory extends ViewRecord
{
    protected static string $resource = LeadHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
