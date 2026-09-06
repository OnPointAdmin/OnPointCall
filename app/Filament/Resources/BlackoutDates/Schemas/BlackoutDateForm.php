<?php

namespace App\Filament\Resources\BlackoutDates\Schemas;

use App\Models\BlackoutDate;
use App\Support\CompanyContext;
use App\Support\UsStates;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BlackoutDateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->rules([
                        fn (Get $get, $livewire): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $livewire): void {
                            $companyId = CompanyContext::idOrAuthenticated();

                            if (! $companyId || blank($value)) {
                                return;
                            }

                            $stateCode = strtoupper(trim((string) $get('state_code'))) ?: UsStates::ALL;

                            $query = BlackoutDate::withoutGlobalScopes()
                                ->where('company_id', $companyId)
                                ->where('state_code', $stateCode)
                                ->whereDate('date', $value);

                            $record = $livewire->getRecord();

                            if ($record !== null) {
                                $query->whereKeyNot($record->getKey());
                            }

                            if ($query->exists()) {
                                $fail('A blackout for this date and state already exists.');
                            }
                        },
                    ]),
                Select::make('state_code')
                    ->label('State')
                    ->options(UsStates::options())
                    ->default(UsStates::ALL)
                    ->required()
                    ->live()
                    ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state)) ?: UsStates::ALL)
                    ->helperText('Choosing a state blocks every lead in that state and every phone number with an area code assigned to that state, even when the lead address is in another state.'),
                TextInput::make('label')
                    ->label('Description')
                    ->placeholder('Labor Day 2026')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
