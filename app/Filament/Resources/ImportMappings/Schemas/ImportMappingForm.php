<?php

namespace App\Filament\Resources\ImportMappings\Schemas;

use App\Filament\Support\LeadTypeSelect;
use App\Services\Import\LeadImportService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ImportMappingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Repeater::make('mapping_rows')
                    ->label('Column mapping')
                    ->table([
                        TableColumn::make('CSV column'),
                        TableColumn::make('Lead field'),
                    ])
                    ->compact()
                    ->schema([
                        TextInput::make('source')
                            ->required(),
                        Select::make('destination')
                            ->options(LeadImportService::KNOWN_IMPORT_FIELDS)
                            ->searchable()
                            ->required(),
                    ])
                    ->addActionLabel('Add row')
                    ->columnSpanFull(),
                LeadTypeSelect::make(),
                Toggle::make('is_default')
                    ->label('Default mapping'),
            ]);
    }
}
