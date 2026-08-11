<?php

namespace App\Filament\Resources\ListAssignments\Pages;

use App\Filament\Resources\ListAssignments\ListAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListListAssignments extends ListRecords
{
    protected static string $resource = ListAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
