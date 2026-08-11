<?php

namespace App\Filament\Resources\LeadClaims\Pages;

use App\Filament\Resources\LeadClaims\LeadClaimResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLeadClaim extends EditRecord
{
    protected static string $resource = LeadClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
