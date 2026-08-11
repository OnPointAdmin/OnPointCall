<?php

namespace App\Filament\Resources\LeadClaims;

use App\Filament\Resources\LeadClaims\Pages\ListLeadClaims;
use App\Filament\Resources\LeadClaims\Pages\ViewLeadClaim;
use App\Filament\Resources\LeadClaims\Schemas\LeadClaimInfolist;
use App\Filament\Resources\LeadClaims\Tables\LeadClaimsTable;
use App\Models\LeadClaim;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LeadClaimResource extends Resource
{
    protected static ?string $model = LeadClaim::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Lead Claims';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    public static function infolist(Schema $schema): Schema
    {
        return LeadClaimInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadClaimsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadClaims::route('/'),
            'view' => ViewLeadClaim::route('/{record}'),
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

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
