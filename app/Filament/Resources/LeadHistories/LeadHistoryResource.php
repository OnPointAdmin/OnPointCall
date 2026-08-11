<?php

namespace App\Filament\Resources\LeadHistories;

use App\Filament\Resources\LeadHistories\Pages\ListLeadHistories;
use App\Filament\Resources\LeadHistories\Pages\ViewLeadHistory;
use App\Filament\Resources\LeadHistories\Schemas\LeadHistoryInfolist;
use App\Filament\Resources\LeadHistories\Tables\LeadHistoriesTable;
use App\Models\LeadHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LeadHistoryResource extends Resource
{
    protected static ?string $model = LeadHistory::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Lead History';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function infolist(Schema $schema): Schema
    {
        return LeadHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadHistoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadHistories::route('/'),
            'view' => ViewLeadHistory::route('/{record}'),
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
