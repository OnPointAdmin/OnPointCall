<?php

namespace App\Filament\Resources\ListAssignments;

use App\Filament\Resources\ListAssignments\Pages\CreateListAssignment;
use App\Filament\Resources\ListAssignments\Pages\EditListAssignment;
use App\Filament\Resources\ListAssignments\Pages\ListListAssignments;
use App\Filament\Resources\ListAssignments\Schemas\ListAssignmentForm;
use App\Filament\Resources\ListAssignments\Tables\ListAssignmentsTable;
use App\Models\ListAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ListAssignmentResource extends Resource
{
    protected static ?string $model = ListAssignment::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Lists';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'List Assignments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return ListAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ListAssignmentsTable::configure($table);
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
            'index' => ListListAssignments::route('/'),
            'create' => CreateListAssignment::route('/create'),
            'edit' => EditListAssignment::route('/{record}/edit'),
        ];
    }
}
