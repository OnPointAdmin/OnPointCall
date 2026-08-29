<?php

namespace App\Filament\Resources\DispositionReasons;

use App\Filament\Resources\Dispositions\DispositionResource;
use App\Filament\Resources\DispositionReasons\Pages\CreateDispositionReason;
use App\Filament\Resources\DispositionReasons\Pages\EditDispositionReason;
use App\Filament\Resources\DispositionReasons\Pages\ListDispositionReasons;
use App\Filament\Resources\DispositionReasons\Schemas\DispositionReasonForm;
use App\Filament\Resources\DispositionReasons\Tables\DispositionReasonsTable;
use App\Models\DispositionReason;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DispositionReasonResource extends Resource
{
    protected static ?string $model = DispositionReason::class;

    protected static ?string $modelLabel = 'Disposition reason';

    protected static ?string $pluralModelLabel = 'Disposition reasons';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $navigationParentItem = DispositionResource::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Disposition Reasons';

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function form(Schema $schema): Schema
    {
        return DispositionReasonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DispositionReasonsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDispositionReasons::route('/'),
            'create' => CreateDispositionReason::route('/create'),
            'edit' => EditDispositionReason::route('/{record}/edit'),
        ];
    }
}
