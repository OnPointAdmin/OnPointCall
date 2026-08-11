<?php

namespace App\Filament\Resources\ImportMappings\Schemas;

use App\Enums\LeadType;
use Filament\Forms\Components\KeyValue;
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
                KeyValue::make('column_map')
                    ->label('Column map')
                    ->keyLabel('Lead field')
                    ->valueLabel('CSV header')
                    ->required()
                    ->helperText('Map lead fields to CSV column headers. Use extra.field_name for TNB/custom columns.')
                    ->columnSpanFull(),
                Select::make('lead_type')
                    ->options(LeadType::class),
                Toggle::make('is_default')
                    ->label('Default mapping'),
            ]);
    }
}
