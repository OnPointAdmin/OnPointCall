<?php

namespace App\Filament\Resources\StateRules;

use App\Filament\Resources\StateRules\Pages\CreateStateRule;
use App\Filament\Resources\StateRules\Pages\EditStateRule;
use App\Filament\Resources\StateRules\Pages\ListStateRules;
use App\Filament\Resources\StateRules\Schemas\StateRuleForm;
use App\Filament\Resources\StateRules\Tables\StateRulesTable;
use App\Models\StateRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StateRuleResource extends Resource
{
    protected static ?string $model = StateRule::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Compliance';

    protected static ?string $navigationLabel = 'State Rules';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'state_code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    public static function form(Schema $schema): Schema
    {
        return StateRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StateRulesTable::configure($table);
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
            'index' => ListStateRules::route('/'),
            'create' => CreateStateRule::route('/create'),
            'edit' => EditStateRule::route('/{record}/edit'),
        ];
    }
}
