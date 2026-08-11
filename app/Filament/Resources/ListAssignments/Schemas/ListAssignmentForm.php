<?php

namespace App\Filament\Resources\ListAssignments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ListAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('calling_list_id')
                    ->relationship('callingList', 'name')
                    ->required(),
            ]);
    }
}
