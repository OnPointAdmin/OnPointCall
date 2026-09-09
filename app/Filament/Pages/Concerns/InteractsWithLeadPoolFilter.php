<?php

namespace App\Filament\Pages\Concerns;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\QualifiedPartnersMatch;
use App\Filament\Support\LeadPoolFilterForm;
use App\Filament\Support\LeadPoolPreviewTable;
use App\Models\Lead;
use App\Services\Import\HoldingReleaseService;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithLeadPoolFilter
{
    public int $holdingCount = 0;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filterData = [];

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearFilters')
                ->label('Clear Filters')
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->action(function (HoldingReleaseService $releaseService): void {
                    $this->clearFilters($releaseService);
                }),
        ];
    }

    public function filterForm(Schema $schema): Schema
    {
        return $schema
            ->components(LeadPoolFilterForm::sections($this))
            ->statePath('filterData');
    }

    public function clearFilters(HoldingReleaseService $releaseService): void
    {
        $reset = array_fill_keys(array_keys($this->filterData ?? []), null);

        $this->filterForm->fill(array_merge($reset, $this->defaultFilterData()));
        $this->refreshCount($releaseService);
    }

    public function refreshCountAction(HoldingReleaseService $releaseService): void
    {
        $this->refreshCount($releaseService);
    }

    public function updatedFilterData(): void
    {
        $this->refreshCount(app(HoldingReleaseService::class));
    }

    public function selectedLeadType(): ?string
    {
        $leadType = $this->filterData['lead_type'] ?? null;

        return $leadType !== null && $leadType !== '' ? (string) $leadType : null;
    }

    public function selectedSourceCallingListId(): ?int
    {
        $source = $this->filterData['source_calling_list_id'] ?? 'holding';

        if ($source === null || $source === '' || $source === 'holding') {
            return null;
        }

        return (int) $source;
    }

    public function leadPoolAssignableOnly(): bool
    {
        return true;
    }

    protected function initializeLeadPoolFilter(HoldingReleaseService $releaseService): void
    {
        $this->filterForm->fill($this->defaultFilterData());
        $this->refreshCount($releaseService);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultFilterData(): array
    {
        return [
            'lead_type' => 'standard',
            'source_calling_list_id' => 'holding',
            'qualified_partners_match' => QualifiedPartnersMatch::InList->value,
        ];
    }

    protected function leadPoolFilterFormComponent(): Form
    {
        return Form::make([EmbeddedSchema::make('filterForm')])
            ->id('filterForm')
            ->livewireSubmitHandler('refreshCountAction')
            ->footer([
                Actions::make([
                    Action::make('applyFilters')
                        ->label('Update count')
                        ->action('refreshCountAction'),
                ]),
            ]);
    }

    protected function configureLeadPoolTable(Table $table): Table
    {
        return LeadPoolPreviewTable::configure($table, fn (): Builder => $this->matchingLeadsQuery());
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

    /**
     * @return Builder<Lead>
     */
    protected function matchingLeadsQuery(): Builder
    {
        return app(HoldingReleaseService::class)
            ->queryMatchingLeads(
                (int) auth()->user()->company_id,
                $this->buildFilter(),
                $this->leadPoolPreviewMaxCount(),
                $this->leadPoolAssignableOnly(),
            )
            ->with(['latestDisposition']);
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
        $data = $this->filterData ?? [];

        return new HoldingFilter(
            leadType: $this->selectedLeadType(),
            sourceCallingListId: $this->selectedSourceCallingListId(),
            state: $this->selectedList($data['state'] ?? null),
            venue: $this->selectedList($data['venue'] ?? null),
            event: $this->selectedList($data['event'] ?? null),
            importBatchId: isset($data['import_batch_id']) ? (int) $data['import_batch_id'] : null,
            importedFrom: $data['imported_from'] ?? null,
            importedTo: $data['imported_to'] ?? null,
            createdFrom: $data['created_from'] ?? null,
            createdTo: $data['created_to'] ?? null,
            zip: $data['zip'] ?? null,
            partner: $this->selectedList($data['partner'] ?? null),
            fileName: $data['file_name'] ?? null,
            softScoreCode: $this->selectedList($data['soft_score_code'] ?? null),
            ageRange: $this->selectedList($data['age_range'] ?? null),
            annualIncome: $this->selectedList($data['annual_income'] ?? null),
            maritalStatus: $this->selectedList($data['marital_status'] ?? null),
            gender: $this->selectedList($data['gender'] ?? null),
            homeOwner: $this->selectedList($data['home_owner'] ?? null),
            tourLocation: $this->selectedList($data['tour_location'] ?? null),
            tourDateStart: $this->selectedList($data['tour_date_start'] ?? null),
            tourDate: $this->selectedList($data['tour_date'] ?? null),
            tourResult: $this->selectedList($data['tour_result'] ?? null),
            qualificationStatus: isset($data['qualification_status']) && $data['qualification_status'] !== ''
                ? (string) $data['qualification_status']
                : null,
            lastDispositions: $this->selectedList($data['last_dispositions'] ?? null),
            attemptCount: isset($data['attempt_count']) && $data['attempt_count'] !== ''
                ? (int) $data['attempt_count']
                : null,
            qualifiedPartners: $this->selectedList($data['qualified_partners'] ?? null),
            qualifiedPartnersMatch: isset($data['qualified_partners_match']) && $data['qualified_partners_match'] !== ''
                ? (string) $data['qualified_partners_match']
                : null,
        );
    }

    /**
     * @return list<string>|null
     */
    protected function selectedList(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $values = is_array($value) ? $value : [$value];
        $normalized = array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $values),
            static fn (string $item): bool => $item !== '',
        ));

        return $normalized === [] ? null : $normalized;
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
