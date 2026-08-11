<?php

namespace App\Filament\Resources\SettingsHistories;

use App\Filament\Resources\SettingsHistories\Pages\ListSettingsHistories;
use App\Filament\Resources\SettingsHistories\Pages\ViewSettingsHistory;
use App\Filament\Resources\SettingsHistories\Schemas\SettingsHistoryInfolist;
use App\Filament\Resources\SettingsHistories\Tables\SettingsHistoriesTable;
use App\Models\SettingsHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SettingsHistoryResource extends Resource
{
    protected static ?string $model = SettingsHistory::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Settings History';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function infolist(Schema $schema): Schema
    {
        return SettingsHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SettingsHistoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettingsHistories::route('/'),
            'view' => ViewSettingsHistory::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
