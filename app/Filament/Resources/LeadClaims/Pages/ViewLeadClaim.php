<?php

namespace App\Filament\Resources\LeadClaims\Pages;

use App\Filament\Resources\LeadClaims\LeadClaimResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLeadClaim extends ViewRecord
{
    protected static string $resource = LeadClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
