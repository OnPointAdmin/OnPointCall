<?php

namespace App\Filament\Resources\Dispositions;

use App\Filament\Resources\Dispositions\Pages\CreateDisposition;
use App\Filament\Resources\Dispositions\Pages\EditDisposition;
use App\Filament\Resources\Dispositions\Pages\ListDispositions;
use App\Filament\Resources\Dispositions\Schemas\DispositionForm;
use App\Filament\Resources\Dispositions\Tables\DispositionsTable;
use App\Models\DispositionDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DispositionResource extends Resource
{
    protected static ?string $model = DispositionDefinition::class;

    protected static ?string $modelLabel = 'Disposition';

    protected static ?string $pluralModelLabel = 'Dispositions';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Dispositions';

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public static function form(Schema $schema): Schema
    {
        return DispositionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DispositionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDispositions::route('/'),
            'create' => CreateDisposition::route('/create'),
            'edit' => EditDisposition::route('/{record}/edit'),
        ];
    }
}
