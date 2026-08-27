<?php

namespace App\Filament\Resources\LeadHistories\Pages;

use App\Filament\Resources\LeadHistories\LeadHistoryResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\LeadHistory;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewLeadHistory extends ViewRecord
{
    protected static string $resource = LeadHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openLead')
                ->label('Open lead')
                ->url(fn (LeadHistory $record): string => LeadResource::getUrl('view', ['record' => $record->lead_id])),
        ];
    }
}
