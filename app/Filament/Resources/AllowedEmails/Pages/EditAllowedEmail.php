<?php

namespace App\Filament\Resources\AllowedEmails\Pages;

use App\Filament\Resources\AllowedEmails\AllowedEmailResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAllowedEmail extends EditRecord
{
    protected static string $resource = AllowedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
