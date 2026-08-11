<?php

namespace App\Filament\Resources\DashboardEmailRecipients\Pages;

use App\Filament\Resources\DashboardEmailRecipients\DashboardEmailRecipientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDashboardEmailRecipients extends ListRecords
{
    protected static string $resource = DashboardEmailRecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
