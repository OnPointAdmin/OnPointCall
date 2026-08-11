<?php

namespace App\Filament\Resources\ImportMappings;

use App\Filament\Resources\ImportMappings\Pages\CreateImportMapping;
use App\Filament\Resources\ImportMappings\Pages\EditImportMapping;
use App\Filament\Resources\ImportMappings\Pages\ListImportMappings;
use App\Filament\Resources\ImportMappings\Schemas\ImportMappingForm;
use App\Filament\Resources\ImportMappings\Tables\ImportMappingsTable;
use App\Models\ImportMapping;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImportMappingResource extends Resource
{
    protected static ?string $model = ImportMapping::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Imports';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    public static function form(Schema $schema): Schema
    {
        return ImportMappingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportMappingsTable::configure($table);
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
            'index' => ListImportMappings::route('/'),
            'create' => CreateImportMapping::route('/create'),
            'edit' => EditImportMapping::route('/{record}/edit'),
        ];
    }
}
