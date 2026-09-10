<?php

namespace App\Filament\Resources\CallingLists\RelationManagers;

use App\Enums\LeadTablePreset;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Filament\Support\LeadTableLayoutSession;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Leads in this list';

    public function getTableColumnsSessionKey(): string
    {
        return LeadTableLayoutSession::sessionKey(LeadTablePreset::CallingList);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadTableColumnsFromSession(): array
    {
        return LeadTableLayoutSession::load($this, LeadTablePreset::CallingList);
    }

    protected function persistTableColumns(): void
    {
        LeadTableLayoutSession::persist($this, LeadTablePreset::CallingList, $this->tableColumns);
    }

    public function resetTableColumnManager(): void
    {
        LeadTableLayoutSession::reset($this, LeadTablePreset::CallingList);
    }

    public function table(Table $table): Table
    {
        return LeadsTable::configure($table, LeadTablePreset::CallingList)
            ->recordTitleAttribute('phone')
            ->heading(null);
    }
}
