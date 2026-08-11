<?php

namespace App\Filament\Resources\LeadClaims\Pages;

use App\Filament\Resources\LeadClaims\LeadClaimResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeadClaims extends ListRecords
{
    protected static string $resource = LeadClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
