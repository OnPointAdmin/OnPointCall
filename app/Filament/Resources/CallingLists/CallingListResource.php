<?php

namespace App\Filament\Resources\CallingLists;

use App\Filament\Resources\CallingLists\Pages\CreateCallingList;
use App\Filament\Resources\CallingLists\Pages\EditCallingList;
use App\Filament\Resources\CallingLists\Pages\ListCallingLists;
use App\Filament\Resources\CallingLists\Schemas\CallingListForm;
use App\Filament\Resources\CallingLists\Tables\CallingListsTable;
use App\Models\CallingList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CallingListResource extends Resource
{
    protected static ?string $model = CallingList::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Lists';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return CallingListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallingListsTable::configure($table);
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
            'index' => ListCallingLists::route('/'),
            'create' => CreateCallingList::route('/create'),
            'edit' => EditCallingList::route('/{record}/edit'),
        ];
    }
}
