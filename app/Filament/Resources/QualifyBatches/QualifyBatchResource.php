<?php

namespace App\Filament\Resources\QualifyBatches;

use App\Filament\Navigation\QualifyNavigation;
use App\Filament\Resources\QualifyBatches\Pages\ListQualifyBatches;
use App\Filament\Resources\QualifyBatches\Pages\ViewQualifyBatch;
use App\Filament\Resources\QualifyBatches\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\QualifyBatches\Schemas\QualifyBatchForm;
use App\Filament\Resources\QualifyBatches\Tables\QualifyBatchesTable;
use App\Models\QualifyBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class QualifyBatchResource extends Resource
{
    protected static ?string $model = QualifyBatch::class;

    protected static string|\UnitEnum|null $navigationGroup = QualifyNavigation::GROUP;

    protected static ?string $navigationParentItem = QualifyNavigation::PARENT;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Qualify Batches';

    protected static ?string $modelLabel = 'Qualify Batch';

    protected static ?string $pluralModelLabel = 'Qualify Batches';

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return QualifyBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QualifyBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LeadsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQualifyBatches::route('/'),
            'view' => ViewQualifyBatch::route('/{record}'),
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
}
