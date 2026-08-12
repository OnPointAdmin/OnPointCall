<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\LeadType;
use App\Enums\SoftScoreStatus;
use App\Exceptions\HoldingReleaseException;
use App\Models\CallingList;
use App\Models\ImportBatch;
use App\Services\Import\HoldingReleaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FreshLeads extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Fresh Leads';

    protected static ?string $title = 'Fresh Leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightCircle;

    protected string $view = 'filament.pages.fresh-leads';

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
            'lead_type' => LeadType::Standard->value,
        ]);

        $this->releaseForm->fill([
            'release_mode' => 'all',
            'fresh_count' => 10,
        ]);

        $this->refreshCount($releaseService);
    }

    public function filterForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filters')
                    ->schema([
                        Select::make('lead_type')
                            ->options(LeadType::class)
                            ->required()
                            ->live(),
                        TextInput::make('state')
                            ->maxLength(2)
                            ->live(debounce: 500),
                        TextInput::make('venue')
                            ->live(debounce: 500),
                        TextInput::make('event')
                            ->live(debounce: 500),
                        Select::make('import_batch_id')
                            ->label('Import batch')
                            ->options(fn () => ImportBatch::query()->orderByDesc('imported_at')->pluck('source_filename', 'id'))
                            ->searchable(),
                        DatePicker::make('imported_from'),
                        DatePicker::make('imported_to'),
                        TextInput::make('zip')
                            ->maxLength(10),
                        TextInput::make('partner'),
                        Select::make('soft_score_status')
                            ->options(SoftScoreStatus::class),
                        TextInput::make('soft_score_code'),
                    ])
                    ->columns(3),
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

                                $query = CallingList::query()->where('active', true);

                                if ($leadType) {
                                    $query->where('lead_type', $leadType);
                                }

                                return $query->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->required()
                            ->searchable(),
                        Radio::make('release_mode')
                            ->options([
                                'all' => 'Assign all matching leads',
                                'fresh' => 'Assign N freshest (by import date)',
                            ])
                            ->default('all')
                            ->live(),
                        TextInput::make('fresh_count')
                            ->label('Number of freshest leads')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn (): bool => ($this->releaseData['release_mode'] ?? 'all') === 'fresh'),
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
            ]);
    }

    public function refreshCountAction(HoldingReleaseService $releaseService): void
    {
        $this->refreshCount($releaseService);
    }

    public function updatedFilterData(): void
    {
        $this->refreshCount(app(HoldingReleaseService::class));
    }

    public function release(HoldingReleaseService $releaseService): void
    {
        $filter = $this->buildFilter();
        $release = $this->releaseForm->getState();

        try {
            $released = match ($release['release_mode']) {
                'fresh' => $releaseService->releaseFresh(
                    auth()->user()->company_id,
                    $filter,
                    (int) $release['calling_list_id'],
                    (int) $release['fresh_count'],
                    auth()->id(),
                ),
                default => $releaseService->releaseAll(
                    auth()->user()->company_id,
                    $filter,
                    (int) $release['calling_list_id'],
                    auth()->id(),
                ),
            };
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

    private function refreshCount(HoldingReleaseService $releaseService): void
    {
        $this->holdingCount = $releaseService->countHolding(
            auth()->user()->company_id,
            $this->buildFilter(),
        );
    }

    private function buildFilter(): HoldingFilter
    {
        $data = $this->filterData ?? [];

        return new HoldingFilter(
            leadType: isset($data['lead_type']) ? LeadType::from($data['lead_type']) : null,
            state: $data['state'] ?? null,
            venue: $data['venue'] ?? null,
            event: $data['event'] ?? null,
            importBatchId: isset($data['import_batch_id']) ? (int) $data['import_batch_id'] : null,
            importedFrom: $data['imported_from'] ?? null,
            importedTo: $data['imported_to'] ?? null,
            zip: $data['zip'] ?? null,
            partner: $data['partner'] ?? null,
            softScoreStatus: $data['soft_score_status'] ?? null,
            softScoreCode: $data['soft_score_code'] ?? null,
        );
    }
}
