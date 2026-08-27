<?php

namespace App\Filament\Resources\LeadHistories\Pages;

use App\Filament\Resources\LeadHistories\LeadHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListLeadHistories extends ListRecords
{
    protected static string $resource = LeadHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
