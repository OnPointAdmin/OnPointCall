<?php

namespace App\Filament\Resources\CallingLists\Schemas;

use App\Filament\Support\LeadTypeSelect;
use Filament\Forms\Components\KeyValue;
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
                LeadTypeSelect::make(),
                Select::make('cadence_id')
                    ->label('Cadence')
                    ->relationship(
                        name: 'cadence',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Timing, day-part rotation, and attempt wait rules come from the selected cadence.'),
                TextInput::make('max_attempts_override')
                    ->numeric(),
                Toggle::make('active')
                    ->required(),
                Textarea::make('booking_url_template')
                    ->columnSpanFull(),
                KeyValue::make('booking_param_map')
                    ->label('Booking URL parameters')
                    ->keyLabel('Form field')
                    ->valueLabel('Lead field')
                    ->helperText('Map booking form fields to lead columns, e.g. 2ff7-7114-0d49 → external_lead_id. Leave empty to send id=external_lead_id.')
                    ->columnSpanFull(),
            ]);
    }
}
