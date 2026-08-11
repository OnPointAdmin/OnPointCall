<?php

namespace App\Filament\Resources\ListAssignments\Pages;

use App\Filament\Resources\ListAssignments\ListAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditListAssignment extends EditRecord
{
    protected static string $resource = ListAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
