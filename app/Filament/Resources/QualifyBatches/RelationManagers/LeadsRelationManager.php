<?php

namespace App\Filament\Resources\QualifyBatches\RelationManagers;

use App\Enums\LeadTablePreset;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Filament\Support\LeadTableLayoutSession;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Leads';

    public function getTableColumnsSessionKey(): string
    {
        return LeadTableLayoutSession::sessionKey(LeadTablePreset::Batch);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadTableColumnsFromSession(): array
    {
        return LeadTableLayoutSession::load($this, LeadTablePreset::Batch);
    }

    protected function persistTableColumns(): void
    {
        LeadTableLayoutSession::persist($this, LeadTablePreset::Batch, $this->tableColumns);
    }

    public function resetTableColumnManager(): void
    {
        LeadTableLayoutSession::reset($this, LeadTablePreset::Batch);
    }

    #[On('qualify-batch-refreshed')]
    public function refreshLeadsTable(): void
    {
        $this->resetTable();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return LeadsTable::configure($table, LeadTablePreset::Batch)
            ->recordTitleAttribute('phone')
            ->defaultSort('phone')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
