<?php

namespace App\Filament\Resources\Cadences\Pages;

use App\Filament\Resources\Cadences\CadenceResource;
use App\Filament\Resources\Cadences\Concerns\ManagesCadenceRecord;
use Filament\Resources\Pages\CreateRecord;

class CreateCadence extends CreateRecord
{
    use ManagesCadenceRecord;

    protected static string $resource = CadenceResource::class;
}
