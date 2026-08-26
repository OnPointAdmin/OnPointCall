<?php

namespace App\Filament\Resources\CallingLists\RelationManagers;

use App\Filament\Resources\Leads\Tables\LeadsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Leads in this list';

    public function table(Table $table): Table
    {
        return LeadsTable::configure($table, forCallingList: true)
            ->recordTitleAttribute('phone')
            ->heading(null);
    }
}
