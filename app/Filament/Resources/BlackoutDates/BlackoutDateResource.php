<?php

namespace App\Filament\Resources\BlackoutDates;

use App\Filament\Resources\BlackoutDates\Pages\CreateBlackoutDate;
use App\Filament\Resources\BlackoutDates\Pages\EditBlackoutDate;
use App\Filament\Resources\BlackoutDates\Pages\ListBlackoutDates;
use App\Filament\Resources\BlackoutDates\Schemas\BlackoutDateForm;
use App\Filament\Resources\BlackoutDates\Tables\BlackoutDatesTable;
use App\Models\BlackoutDate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BlackoutDateResource extends Resource
{
    protected static ?string $model = BlackoutDate::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return BlackoutDateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlackoutDatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlackoutDates::route('/'),
            'create' => CreateBlackoutDate::route('/create'),
            'edit' => EditBlackoutDate::route('/{record}/edit'),
        ];
    }
}
