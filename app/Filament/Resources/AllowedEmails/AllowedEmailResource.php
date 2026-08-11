<?php

namespace App\Filament\Resources\AllowedEmails;

use App\Filament\Resources\AllowedEmails\Pages\CreateAllowedEmail;
use App\Filament\Resources\AllowedEmails\Pages\EditAllowedEmail;
use App\Filament\Resources\AllowedEmails\Pages\ListAllowedEmails;
use App\Filament\Resources\AllowedEmails\Schemas\AllowedEmailForm;
use App\Filament\Resources\AllowedEmails\Tables\AllowedEmailsTable;
use App\Models\AllowedEmail;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AllowedEmailResource extends Resource
{
    protected static ?string $model = AllowedEmail::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'email';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function form(Schema $schema): Schema
    {
        return AllowedEmailForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AllowedEmailsTable::configure($table);
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
            'index' => ListAllowedEmails::route('/'),
            'create' => CreateAllowedEmail::route('/create'),
            'edit' => EditAllowedEmail::route('/{record}/edit'),
        ];
    }
}
