<?php

namespace App\Filament\Resources\StateRules\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StateRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('state_code')
                    ->required(),
                TimePicker::make('window_start')
                    ->required(),
                TimePicker::make('window_end')
                    ->required(),
                TextInput::make('permitted_weekdays')
                    ->required(),
                Toggle::make('manual_dial_only')
                    ->required(),
            ]);
    }
}
