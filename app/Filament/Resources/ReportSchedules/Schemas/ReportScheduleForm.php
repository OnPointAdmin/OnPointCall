<?php

namespace App\Filament\Resources\ReportSchedules\Schemas;

use App\Enums\LeadSourceGroupBy;
use App\Enums\ReportSchedulePeriod;
use App\Enums\ReportScheduleType;
use App\Enums\UserRole;
use App\Filament\Support\LeadTypeSelect;
use App\Models\CallingList;
use App\Models\DispositionDefinition;
use App\Models\User;
use App\Services\Dashboard\CallDetailReportService;
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
                Section::make('Filters')
                    ->columnSpanFull()
                    ->description('Leave blank to include all reps, lists, and dispositions.')
                    ->visible(fn (Get $get): bool => self::selectedType($get)?->usesFilters() ?? false)
                    ->schema([
                        Select::make('filters.agent_id')
                            ->label('Rep')
                            ->options(fn (): array => ['' => 'All'] + User::query()
                                ->where('role', UserRole::Agent)
                                ->where('active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->nullable(),
                        LeadTypeSelect::make('filters.lead_type', allowCreate: false)
                            ->required(false)
                            ->nullable()
                            ->placeholder('All'),
                        Select::make('filters.calling_list_id')
                            ->label('Calling list')
                            ->options(fn (): array => ['' => 'All', 'holding' => 'Holding'] + CallingList::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->nullable()
                            ->placeholder('All'),
                        Select::make('filters.dispositions')
                            ->label('Dispositions')
                            ->options(fn (): array => DispositionDefinition::filterOptions(
                                (int) auth()->user()->company_id,
                            ))
                            ->multiple()
                            ->searchable()
                            ->nullable()
                            ->placeholder('All')
                            ->columnSpanFull(),
                        Select::make('filters.columns')
                            ->label('Columns')
                            ->options(fn (): array => CallDetailReportService::columnOptions())
                            ->multiple()
                            ->searchable()
                            ->reorderable()
                            ->nullable()
                            ->placeholder('Standard CSV columns')
                            ->helperText('Leave blank for the standard CSV. Choose any fields and drag selected chips to set CSV order.')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
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
