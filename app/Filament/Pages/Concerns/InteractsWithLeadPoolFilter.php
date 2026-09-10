<?php

namespace App\Filament\Pages\Concerns;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\LeadTablePreset;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Filament\Support\LeadTableFilterMapper;
use App\Models\Lead;
use App\Services\Import\HoldingReleaseService;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithLeadPoolFilter
{
    public int $holdingCount = 0;

    abstract protected function leadPoolPreset(): LeadTablePreset;

    protected function leadTablePreset(): LeadTablePreset
    {
        return $this->leadPoolPreset();
    }

    public function mountLeadPoolTable(): void
    {
        $this->tableFilters = $this->leadPoolPreset()->defaultTableFilters();
    }

    public function mountInteractsWithTable(): void
    {
        $this->filamentMountInteractsWithTable();

        if (blank($this->tableFilters)) {
            $this->tableFilters = $this->leadPoolPreset()->defaultTableFilters();
        }
    }

    public function bootedInteractsWithLeadPoolFilter(): void
    {
        if (blank($this->tableFilters)) {
            $this->tableFilters = $this->leadPoolPreset()->defaultTableFilters();
        }

        if (! isset($this->table)) {
            return;
        }

        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->refreshCount(app(HoldingReleaseService::class));
    }

    public function updatedTableFilters(): void
    {
        $this->handleTableFilterUpdates();
        $this->refreshCount(app(HoldingReleaseService::class));
    }

    public function resetTableFiltersForm(): void
    {
        $this->tableFilters = [];
        $this->tableDeferredFilters = null;

        if ($this->getTable()->hasDeferredFilters()) {
            $this->tableFilters = $this->leadPoolPreset()->defaultTableFilters();
            $this->getTableFiltersForm()->fill($this->tableFilters);
        } else {
            $this->tableFilters = $this->leadPoolPreset()->defaultTableFilters();
        }

        $this->handleTableFilterUpdates();
        $this->refreshCount(app(HoldingReleaseService::class));
    }

    public function leadPoolAssignableOnly(): bool
    {
        return true;
    }

    public function selectedLeadType(): ?string
    {
        $leadType = $this->tableFilters['lead_type']['value'] ?? null;

        return $leadType !== null && $leadType !== '' ? (string) $leadType : null;
    }

    public function selectedSourceCallingListId(): ?int
    {
        $source = $this->tableFilters['calling_list_id']['value'] ?? 'holding';

        if ($source === null || $source === '' || $source === 'holding') {
            return null;
        }

        return (int) $source;
    }

    protected function leadPoolTableSection(): Section
    {
        return Section::make('Selected leads')
            ->description(fn (): string => $this->selectedLeadsDescription())
            ->schema([
                EmbeddedTable::make(),
            ]);
    }

    protected function configureLeadPoolTable(Table $table): Table
    {
        return LeadsTable::configure($table, $this->leadPoolPreset())
            ->query(fn (): Builder => $this->poolLeadsQuery())
            ->heading(null)
            ->emptyStateHeading('No matching leads')
            ->emptyStateDescription('Adjust the filters above to select leads.');
    }

    protected function poolLeadsQuery(): Builder
    {
        return app(HoldingReleaseService::class)->queryHolding(
            (int) auth()->user()->company_id,
            $this->buildFilter(),
            $this->leadPoolAssignableOnly(),
        )->with(['latestDisposition']);
    }

    public function getFilteredTableQuery(): ?Builder
    {
        if (! isset($this->table)) {
            return null;
        }

        $query = $this->poolLeadsQuery();

        return $this->applySearchToTableQuery($query);
    }

    protected function refreshCount(HoldingReleaseService $releaseService): void
    {
        $this->holdingCount = $releaseService->countHolding(
            (int) auth()->user()->company_id,
            $this->buildFilter(),
            $this->leadPoolAssignableOnly(),
        );

        $this->resetSelectedLeadsTable();
    }

    protected function resetSelectedLeadsTable(): void
    {
        if (! isset($this->table)) {
            return;
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function getFilteredSortedTableQuery(): ?Builder
    {
        $query = $this->filamentGetFilteredSortedTableQuery();

        if (! $query) {
            return null;
        }

        $maxCount = $this->leadPoolPreviewMaxCount();

        if ($maxCount === null) {
            return $query;
        }

        $ids = (clone $query)
            ->reorder()
            ->orderByDesc('imported_at')
            ->limit($maxCount)
            ->pluck('id');

        return Lead::query()->whereIn('id', $ids);
    }

    protected function selectedLeadsDescription(): string
    {
        $maxCount = $this->leadPoolPreviewMaxCount();

        if ($this->holdingCount === 0) {
            return 'No matching leads.';
        }

        if ($maxCount !== null && $maxCount < $this->holdingCount) {
            return "The {$maxCount} freshest of {$this->holdingCount} matching leads.";
        }

        return "All {$this->holdingCount} matching leads.";
    }

    protected function leadPoolPreviewMaxCount(): ?int
    {
        return null;
    }

    protected function buildFilter(): HoldingFilter
    {
        return LeadTableFilterMapper::toHoldingFilter(
            $this->tableFilters ?? $this->leadPoolPreset()->defaultTableFilters(),
        );
    }

    protected function maxCountFromValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $count = (int) $value;

        return $count >= 1 ? $count : null;
    }
}
