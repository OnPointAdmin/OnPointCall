<?php

namespace App\Filament\Resources\CallingLists\Pages;

use App\Filament\Resources\CallingLists\CallingListResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCallingList extends EditRecord
{
    protected static string $resource = CallingListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getAllRelationManagers(): array
    {
        return [];
    }
}
