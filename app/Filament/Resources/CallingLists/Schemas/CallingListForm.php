<?php

namespace App\Filament\Resources\CallingLists\Schemas;

use App\Enums\LeadType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CallingListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('lead_type')
                    ->options(LeadType::class)
                    ->required(),
                TextInput::make('cadence')
                    ->required()
                    ->default('{}'),
                TextInput::make('max_attempts_override')
                    ->numeric(),
                Toggle::make('active')
                    ->required(),
                Textarea::make('booking_url_template')
                    ->columnSpanFull(),
                TextInput::make('booking_param_map'),
            ]);
    }
}
