<?php

namespace App\Filament\Resources\Cadences\Schemas;

use App\Enums\CadenceWaitUnit;
use App\Support\CadenceDefaults;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CadenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
                Toggle::make('prioritize_unattempted')
                    ->label('Prioritize never-dialed leads')
                    ->helperText('Leads with zero attempts are served before retried leads on lists using this cadence.')
                    ->default(true)
                    ->required()
                    ->columnSpanFull(),
                Section::make('Day parts')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('dayParts')
                            ->relationship()
                            ->extraAttributes(['class' => 'fi-cadence-day-parts-table'])
                            ->table([
                                TableColumn::make('Day part')->alignStart(),
                                TableColumn::make('In rotation')->alignStart(),
                                TableColumn::make('Start')->alignStart(),
                                TableColumn::make('End')->alignStart(),
                                TableColumn::make('Wait after dial')->alignStart(),
                                TableColumn::make('Unit')->alignStart(),
                            ])
                            ->schema([
                                Placeholder::make('day_part_label')
                                    ->label('Day part')
                                    ->content(fn (Get $get): string => CadenceDefaults::label((string) $get('day_part'))),
                                TextInput::make('day_part')
                                    ->hidden()
                                    ->dehydrated(),
                                Toggle::make('enabled')
                                    ->label('In rotation'),
                                TimePicker::make('window_start')
                                    ->label('Start')
                                    ->seconds(false)
                                    ->required(),
                                TimePicker::make('window_end')
                                    ->label('End')
                                    ->seconds(false)
                                    ->required(),
                                TextInput::make('wait_after_value')
                                    ->label('Wait after dial')
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable(),
                                Select::make('wait_after_unit')
                                    ->label('Unit')
                                    ->options(collect(CadenceWaitUnit::cases())->mapWithKeys(
                                        fn (CadenceWaitUnit $unit): array => [$unit->value => $unit->label()],
                                    )->all())
                                    ->nullable(),
                            ])
                            ->reorderable()
                            ->orderColumn('rotation_order')
                            ->addable(false)
                            ->deletable(false)
                            ->compact()
                            ->columnSpanFull()
                            ->helperText(fn (Get $get): string => self::rotationPreview($get('dayParts') ?? []).' Leave wait blank to allow the next window the same day.'),
                    ]),
                Section::make('Attempt wait rules')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('attemptGaps')
                            ->relationship()
                            ->table([
                                TableColumn::make('After attempt #'),
                                TableColumn::make('Wait'),
                                TableColumn::make('Unit'),
                            ])
                            ->schema([
                                TextInput::make('after_attempt')
                                    ->label('After attempt #')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                                TextInput::make('wait_value')
                                    ->label('Wait')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                                Select::make('wait_unit')
                                    ->label('Unit')
                                    ->options(collect(CadenceWaitUnit::cases())->mapWithKeys(
                                        fn (CadenceWaitUnit $unit): array => [$unit->value => $unit->label()],
                                    )->all())
                                    ->required(),
                            ])
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->compact()
                            ->columnSpanFull()
                            ->helperText('Once a lead completes N attempts, it cannot be called again until this wait passes (in addition to day-part rules).'),
                    ]),
            ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function rotationPreview(array $rows): string
    {
        $enabled = collect($rows)
            ->filter(fn (array $row): bool => (bool) ($row['enabled'] ?? false))
            ->sortBy('rotation_order')
            ->map(fn (array $row): string => CadenceDefaults::label((string) ($row['day_part'] ?? '')))
            ->filter()
            ->values();

        if ($enabled->isEmpty()) {
            return 'Rotation: none enabled';
        }

        return 'Rotation: '.$enabled->implode(' → ').' → …';
    }
}
