<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Actions\ChangeNextDayPartAction;
use App\Filament\Actions\ViewDncResultAction;
use App\Filament\Actions\ViewQualificationResultAction;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewQualificationResultAction::make()
                ->label('Qualification result')
                ->visible(fn (Lead $record): bool => $record->qualification_status !== null),
            ViewDncResultAction::make()
                ->label('DNC result')
                ->visible(fn (Lead $record): bool => $record->dnc_status !== null),
            ChangeNextDayPartAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
