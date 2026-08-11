<?php

namespace App\Filament\Resources\DashboardEmailRecipients;

use App\Filament\Resources\DashboardEmailRecipients\Pages\CreateDashboardEmailRecipient;
use App\Filament\Resources\DashboardEmailRecipients\Pages\EditDashboardEmailRecipient;
use App\Filament\Resources\DashboardEmailRecipients\Pages\ListDashboardEmailRecipients;
use App\Filament\Resources\DashboardEmailRecipients\Schemas\DashboardEmailRecipientForm;
use App\Filament\Resources\DashboardEmailRecipients\Tables\DashboardEmailRecipientsTable;
use App\Models\DashboardEmailRecipient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DashboardEmailRecipientResource extends Resource
{
    protected static ?string $model = DashboardEmailRecipient::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Dashboard Email Recipients';

    protected static ?string $recordTitleAttribute = 'email';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    public static function form(Schema $schema): Schema
    {
        return DashboardEmailRecipientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DashboardEmailRecipientsTable::configure($table);
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
            'index' => ListDashboardEmailRecipients::route('/'),
            'create' => CreateDashboardEmailRecipient::route('/create'),
            'edit' => EditDashboardEmailRecipient::route('/{record}/edit'),
        ];
    }
}
