<?php

namespace App\Filament\Resources\Cadences;

use App\Filament\Resources\Cadences\Pages\CreateCadence;
use App\Filament\Resources\Cadences\Pages\EditCadence;
use App\Filament\Resources\Cadences\Pages\ListCadences;
use App\Filament\Resources\Cadences\Schemas\CadenceForm;
use App\Filament\Resources\Cadences\Tables\CadencesTable;
use App\Models\Cadence;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CadenceResource extends Resource
{
    protected static ?string $model = Cadence::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function form(Schema $schema): Schema
    {
        return CadenceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CadencesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCadences::route('/'),
            'create' => CreateCadence::route('/create'),
            'edit' => EditCadence::route('/{record}/edit'),
        ];
    }
}
