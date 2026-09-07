<?php

namespace App\Filament\Resources\ReportSchedules;

use App\Filament\Navigation\DashboardNavigation;
use App\Filament\Resources\ReportSchedules\Pages\CreateReportSchedule;
use App\Filament\Resources\ReportSchedules\Pages\EditReportSchedule;
use App\Filament\Resources\ReportSchedules\Pages\ListReportSchedules;
use App\Filament\Resources\ReportSchedules\Schemas\ReportScheduleForm;
use App\Filament\Resources\ReportSchedules\Tables\ReportSchedulesTable;
use App\Models\ReportSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReportScheduleResource extends Resource
{
    protected static ?string $model = ReportSchedule::class;

    protected static string|\UnitEnum|null $navigationGroup = DashboardNavigation::GROUP;

    protected static ?string $navigationParentItem = DashboardNavigation::PARENT_REPORTS;

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Report Scheduler';

    protected static ?string $modelLabel = 'report schedule';

    protected static ?string $pluralModelLabel = 'report schedules';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function form(Schema $schema): Schema
    {
        return ReportScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportSchedulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportSchedules::route('/'),
            'create' => CreateReportSchedule::route('/create'),
            'edit' => EditReportSchedule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount('recipients');
    }
}
