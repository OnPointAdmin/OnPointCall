<?php

namespace App\Filament\Resources\ReportSchedules\Schemas;

use App\Enums\LeadSourceGroupBy;
use App\Enums\ReportSchedulePeriod;
use App\Enums\ReportScheduleType;
use App\Support\Weekdays;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ReportScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                Toggle::make('enabled')
                    ->default(true)
                    ->required(),
                Select::make('report_type')
                    ->label('Report')
                    ->options(ReportScheduleType::class)
                    ->default(ReportScheduleType::AgentDashboard->value)
                    ->required()
                    ->live(),
                Select::make('period')
                    ->label('Date range')
                    ->options(ReportSchedulePeriod::class)
                    ->default(ReportSchedulePeriod::Yesterday->value)
                    ->required(fn (Get $get): bool => self::selectedType($get)?->usesPeriod() ?? true)
                    ->visible(fn (Get $get): bool => self::selectedType($get)?->usesPeriod() ?? true)
                    ->helperText('Lead Dashboard is always a live snapshot.'),
                Select::make('group_by')
                    ->label('Group by')
                    ->options(LeadSourceGroupBy::class)
                    ->default(LeadSourceGroupBy::VenueAndEvent->value)
                    ->required(fn (Get $get): bool => self::selectedType($get) === ReportScheduleType::LeadSource)
                    ->visible(fn (Get $get): bool => self::selectedType($get) === ReportScheduleType::LeadSource),
                Select::make('days_of_week')
                    ->label('Days')
                    ->multiple()
                    ->options(Weekdays::options())
                    ->default([1, 2, 3, 4, 5, 6, 7])
                    ->required()
                    ->columnSpanFull(),
                Repeater::make('send_times')
                    ->label('Send times')
                    ->simple(
                        TimePicker::make('time')
                            ->seconds(false)
                            ->required(),
                    )
                    ->minItems(1)
                    ->default(['07:00'])
                    ->addActionLabel('Add send time')
                    ->columnSpanFull(),
                Section::make('Recipients')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('recipients')
                            ->relationship()
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email address')
                                    ->email()
                                    ->required(),
                            ])
                            ->minItems(1)
                            ->addActionLabel('Add recipient')
                            ->defaultItems(1),
                    ]),
            ]);
    }

    private static function selectedType(Get $get): ?ReportScheduleType
    {
        $value = $get('report_type');

        if ($value instanceof ReportScheduleType) {
            return $value;
        }

        return is_string($value) && $value !== ''
            ? ReportScheduleType::tryFrom($value)
            : null;
    }
}
