<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Enums\LeadTablePreset;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Support\LeadTableLayoutSession;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTableColumnsSessionKey(): string
    {
        return LeadTableLayoutSession::sessionKey(LeadTablePreset::AllLeads);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadTableColumnsFromSession(): array
    {
        return LeadTableLayoutSession::load($this, LeadTablePreset::AllLeads);
    }

    protected function persistTableColumns(): void
    {
        LeadTableLayoutSession::persist($this, LeadTablePreset::AllLeads, $this->tableColumns);
    }

    public function resetTableColumnManager(): void
    {
        LeadTableLayoutSession::reset($this, LeadTablePreset::AllLeads);
    }
}
