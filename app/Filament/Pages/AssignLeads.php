<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\Disposition;
use App\Enums\QualificationStatus;
use App\Exceptions\HoldingReleaseException;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Support\LeadTypeSelect;
use App\Models\CallingList;
use App\Models\DispositionDefinition;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Services\Import\HoldingReleaseService;
use App\Support\LeadDemographicOptions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignLeads extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Assign Leads';

    protected static ?string $title = 'Assign Leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightCircle;

    protected string $view = 'filament.pages.assign-leads';

    public int $holdingCount = 0;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filterData = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $releaseData = [];

    public function mount(HoldingReleaseService $releaseService): void
    {
        $this->filterForm->fill([
            'lead_type' => 'standard',
            'source_calling_list_id' => 'holding',
        ]);

        $this->releaseForm->fill([
            'max_count' => null,
        ]);

        $this->refreshCount($releaseService);
    }

    public function filterForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Import')
                    ->schema([
                        LeadTypeSelect::make(allowCreate: true)->live(),
                        Select::make('source_calling_list_id')
                            ->label('Source')
                            ->options(fn (): array => $this->sourceOptions())
                            ->default('holding')
                            ->live(),
                        Select::make('import_batch_id')
                            ->label('Import batch')
                            ->options(fn () => ImportBatch::query()->orderByDesc('imported_at')->pluck('source_filename', 'id'))
                            ->searchable(),
                        TextInput::make('file_name')
                            ->label('Source file')
                            ->live(debounce: 500),
                        DatePicker::make('imported_from')
                            ->label('Import start date'),
                        DatePicker::make('imported_to')
                            ->label('Import end date'),
                        DatePicker::make('created_from')
                            ->label('Start Create Date'),
                        DatePicker::make('created_to')
                            ->label('End Create Date'),
                    ])
                    ->columns(3),
                Section::make('Venue & event')
                    ->schema([
                        $this->holdingSelect('venue', 'Venue'),
                        $this->holdingSelect('event', 'Event'),
                        Select::make('partner')
                            ->label('Partner')
                            ->options(fn () => app(HoldingReleaseService::class)->distinctHoldingPartners(
                                auth()->user()->company_id,
                                $this->selectedLeadType(),
                                $this->selectedSourceCallingListId(),
                            ))
                            ->multiple()
                            ->searchable()
                            ->live(),
                    ])
                    ->columns(3),
                Section::make('Lead profile')
                    ->schema([
                        $this->demographicSelect('age_range', 'Age range'),
                        $this->demographicSelect('annual_income', 'Income range'),
                        $this->demographicSelect('marital_status', 'Marital status'),
                        $this->demographicSelect('gender', 'Gender'),
                        $this->demographicSelect('home_owner', 'Home owner'),
                        $this->holdingSelect('state', 'State'),
                        TextInput::make('zip')
                            ->label('Zip')
                            ->maxLength(10)
                            ->live(debounce: 500),
                        $this->holdingSelect('soft_score_code', 'Soft score code'),
                        Select::make('last_dispositions')
                            ->label('Last Disp')
                            ->options($this->lastDispositionOptions())
                            ->multiple()
                            ->searchable()
                            ->live(),
                        TextInput::make('attempt_count')
                            ->label('Attempts')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->nullable()
                            ->live(debounce: 500),
                        Select::make('qualification_status')
                            ->label('Qualification Status')
                            ->options([
                                QualificationStatus::Qualified->value => QualificationStatus::Qualified->label(),
                                QualificationStatus::NotQualified->value => QualificationStatus::NotQualified->label(),
                            ])
                            ->nullable()
                            ->placeholder('Any')
                            ->live(),
                    ])
                    ->columns(3),
                Section::make('Tour Info')
                    ->schema([
                        $this->holdingSelect('tour_location', 'Tour Location'),
                        $this->holdingSelect('tour_date_start', 'Tour Date Start'),
                        $this->holdingSelect('tour_date', 'Tour Date'),
                        $this->holdingSelect('tour_result', 'Tour Result'),
                    ])
                    ->columns(3)
                    ->visible(fn (): bool => $this->selectedLeadType() === 'tnb'),
            ])
            ->statePath('filterData');
    }

    public function releaseForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assign to List')
                    ->schema([
                        Select::make('calling_list_id')
                            ->label('Target calling list')
                            ->options(function (): array {
                                $leadType = $this->filterData['lead_type'] ?? null;
                                $sourceCallingListId = $this->selectedSourceCallingListId();

                                $query = CallingList::query()->where('active', true);

                                if ($leadType) {
                                    $query->where('lead_type', $leadType);
                                }

                                if ($sourceCallingListId !== null) {
                                    $query->where('id', '!=', $sourceCallingListId);
                                }

                                return $query->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->required()
                            ->searchable(),
                        TextInput::make('max_count')
                            ->label('Max Count')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->nullable()
                            ->live(debounce: 500)
                            ->helperText('Leave empty to assign all matching leads. Enter a number to assign that many freshest leads.'),
                    ])
                    ->columns(1),
            ])
            ->statePath('releaseData');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('filterForm')])
                    ->id('filterForm')
                    ->livewireSubmitHandler('refreshCountAction')
                    ->footer([
                        Actions::make([
                            Action::make('applyFilters')
                                ->label('Update count')
                                ->action('refreshCountAction'),
                        ]),
                    ]),
                Form::make([EmbeddedSchema::make('releaseForm')])
                    ->id('releaseForm')
                    ->livewireSubmitHandler('release')
                    ->footer([
                        Actions::make([
                            Action::make('release')
                                ->label('Assign Leads')
                                ->submit('release')
                                ->color('primary'),
                        ]),
                    ]),
                Section::make('Selected leads')
                    ->description(fn (): string => $this->selectedLeadsDescription())
                    ->schema([
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->matchingLeadsQuery())
            ->heading(null)
            ->columns([
                TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('venue')
                    ->toggleable(),
                TextColumn::make('event')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('last_disposition')
                    ->label('Last Disp')
                    ->badge()
                    ->placeholder('—')
                    ->getStateUsing(function (Lead $record): ?string {
                        $value = $record->latestDisposition?->payload['disposition'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return null;
                        }

                        return DispositionDefinition::labelForSlug($record->company_id, $value) ?? $value;
                    }),
                TextColumn::make('attempt_count')
                    ->label('Attempts')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('file_name')
                    ->label('Source file')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('imported_at', 'desc')
            ->recordUrl(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record]))
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No matching leads')
            ->emptyStateDescription('Adjust the filters above to select leads.');
    }

    public function refreshCountAction(HoldingReleaseService $releaseService): void
    {
        $this->refreshCount($releaseService);
    }

    public function updatedFilterData(): void
    {
        $this->refreshCount(app(HoldingReleaseService::class));
    }

    public function updatedReleaseData(): void
    {
        $this->resetSelectedLeadsTable();
    }

    public function release(HoldingReleaseService $releaseService): void
    {
        $filter = $this->buildFilter();
        $release = $this->releaseForm->getState();
        $maxCount = $this->maxCountFromRelease($release);

        try {
            $released = $maxCount === null
                ? $releaseService->releaseAll(
                    auth()->user()->company_id,
                    $filter,
                    (int) $release['calling_list_id'],
                    auth()->id(),
                )
                : $releaseService->releaseFresh(
                    auth()->user()->company_id,
                    $filter,
                    (int) $release['calling_list_id'],
                    $maxCount,
                    auth()->id(),
                );
        } catch (HoldingReleaseException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->refreshCount($releaseService);

        Notification::make()
            ->title("Assigned {$released} lead(s)")
            ->success()
            ->send();
    }

    private function holdingSelect(string $column, string $label): Select
    {
        return Select::make($column)
            ->label($label)
            ->options(fn () => app(HoldingReleaseService::class)->distinctHoldingColumn(
                auth()->user()->company_id,
                $this->selectedLeadType(),
                $column,
                $this->selectedSourceCallingListId(),
            ))
            ->multiple()
            ->searchable()
            ->live();
    }

    private function demographicSelect(string $column, string $label): Select
    {
        return Select::make($column)
            ->label($label)
            ->options(function () use ($column): array {
                $values = LeadDemographicOptions::for($column, auth()->user()->company_id);

                return $values === [] ? [] : array_combine($values, $values);
            })
            ->multiple()
            ->searchable()
            ->live();
    }

    private function selectedLeadType(): ?string
    {
        $leadType = $this->filterData['lead_type'] ?? null;

        return $leadType !== null && $leadType !== '' ? (string) $leadType : null;
    }

    private function selectedSourceCallingListId(): ?int
    {
        $source = $this->filterData['source_calling_list_id'] ?? 'holding';

        if ($source === null || $source === '' || $source === 'holding') {
            return null;
        }

        return (int) $source;
    }

    /**
     * @return array<string, string>
     */
    private function sourceOptions(): array
    {
        $options = ['holding' => 'Holding'];
        $leadType = $this->filterData['lead_type'] ?? null;

        $query = CallingList::query()->where('active', true);

        if ($leadType) {
            $query->where('lead_type', $leadType);
        }

        foreach ($query->orderBy('name')->get() as $list) {
            $options[(string) $list->id] = $list->name;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function lastDispositionOptions(): array
    {
        $options = ['none' => 'None'];

        foreach (Disposition::cases() as $disposition) {
            $options[$disposition->value] = $disposition->label();
        }

        return $options;
    }

    private function refreshCount(HoldingReleaseService $releaseService): void
    {
        $this->holdingCount = $releaseService->countHolding(
            auth()->user()->company_id,
            $this->buildFilter(),
        );

        $this->resetSelectedLeadsTable();
    }

    private function resetSelectedLeadsTable(): void
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
    private function matchingLeadsQuery(): Builder
    {
        return app(HoldingReleaseService::class)
            ->queryMatchingLeads(
                auth()->user()->company_id,
                $this->buildFilter(),
                $this->previewMaxCount(),
            )
            ->with(['latestDisposition']);
    }

    private function selectedLeadsDescription(): string
    {
        $maxCount = $this->previewMaxCount();

        if ($this->holdingCount === 0) {
            return 'No matching leads.';
        }

        if ($maxCount !== null && $maxCount < $this->holdingCount) {
            return "The {$maxCount} freshest of {$this->holdingCount} matching leads.";
        }

        return "All {$this->holdingCount} matching leads.";
    }

    private function previewMaxCount(): ?int
    {
        $value = $this->releaseData['max_count'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        $count = (int) $value;

        return $count >= 1 ? $count : null;
    }

    private function buildFilter(): HoldingFilter
    {
        $data = $this->filterData ?? [];

        return new HoldingFilter(
            leadType: isset($data['lead_type']) && $data['lead_type'] !== ''
                ? (string) $data['lead_type']
                : null,
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
        );
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function maxCountFromRelease(array $release): ?int
    {
        $value = $release['max_count'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return list<string>|null
     */
    private function selectedList(mixed $value): ?array
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
}
