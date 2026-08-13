<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
                TextInput::make('salesforce_id')
                    ->label('Salesforce ID')
                    ->maxLength(18)
                    ->unique(ignoreRecord: true),
            ]);
    }
}
