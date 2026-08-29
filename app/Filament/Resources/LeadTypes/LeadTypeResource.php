<?php

namespace App\Filament\Resources\LeadTypes;

use App\Filament\Resources\LeadTypes\Pages\CreateLeadType;
use App\Filament\Resources\LeadTypes\Pages\EditLeadType;
use App\Filament\Resources\LeadTypes\Pages\ListLeadTypes;
use App\Filament\Resources\LeadTypes\Schemas\LeadTypeForm;
use App\Filament\Resources\LeadTypes\Tables\LeadTypesTable;
use App\Models\LeadTypeDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LeadTypeResource extends Resource
{
    protected static ?string $model = LeadTypeDefinition::class;

    protected static ?string $modelLabel = 'Lead type';

    protected static ?string $pluralModelLabel = 'Lead types';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return LeadTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadTypesTable::configure($table);
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
            'index' => ListLeadTypes::route('/'),
            'create' => CreateLeadType::route('/create'),
            'edit' => EditLeadType::route('/{record}/edit'),
        ];
    }
}
