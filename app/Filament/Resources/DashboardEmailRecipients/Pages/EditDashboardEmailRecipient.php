<?php

namespace App\Filament\Resources\DashboardEmailRecipients\Pages;

use App\Filament\Resources\DashboardEmailRecipients\DashboardEmailRecipientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDashboardEmailRecipient extends EditRecord
{
    protected static string $resource = DashboardEmailRecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
