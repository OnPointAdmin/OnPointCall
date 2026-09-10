<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\LeadTablePreset;
use App\Filament\Support\LeadTableLayoutSession;

trait InteractsWithLeadTableLayout
{
    abstract protected function leadTablePreset(): LeadTablePreset;

    public function getTableColumnsSessionKey(): string
    {
        return LeadTableLayoutSession::sessionKey($this->leadTablePreset());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadTableColumnsFromSession(): array
    {
        return LeadTableLayoutSession::load($this, $this->leadTablePreset());
    }

    protected function persistTableColumns(): void
    {
        LeadTableLayoutSession::persist($this, $this->leadTablePreset(), $this->tableColumns);
    }

    public function resetTableColumnManager(): void
    {
        LeadTableLayoutSession::reset($this, $this->leadTablePreset());
    }
}
