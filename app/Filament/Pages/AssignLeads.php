<?php

namespace App\Filament\Pages;

use App\Enums\LeadTablePreset;
use App\Exceptions\HoldingReleaseException;
use App\Filament\Pages\Concerns\InteractsWithLeadPoolFilter;
use App\Models\CallingList;
use App\Services\Import\HoldingReleaseService;
use BackedEnum;
use Filament\Actions\Action;
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
use App\Filament\Pages\Concerns\InteractsWithPersistedLeadTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AssignLeads extends Page implements HasTable
{
    use InteractsWithPersistedLeadTable, InteractsWithLeadPoolFilter {
        InteractsWithLeadPoolFilter::updatedTableFilters insteadof InteractsWithPersistedLeadTable;
        InteractsWithLeadPoolFilter::resetTableFiltersForm insteadof InteractsWithPersistedLeadTable;
        InteractsWithLeadPoolFilter::getFilteredSortedTableQuery insteadof InteractsWithPersistedLeadTable;
        InteractsWithLeadPoolFilter::getFilteredTableQuery insteadof InteractsWithPersistedLeadTable;
        InteractsWithLeadPoolFilter::mountInteractsWithTable insteadof InteractsWithPersistedLeadTable;
    }

    protected static string|\UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Assign Leads';

    protected static ?string $title = 'Assign Leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightCircle;

    protected string $view = 'filament.pages.lead-pool';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $releaseData = [];

    public function mount(): void
    {
        $this->mountLeadPoolTable();
        $this->refreshCount(app(HoldingReleaseService::class));

        $this->releaseForm->fill([
            'max_count' => null,
        ]);
    }

    protected function leadPoolPreset(): LeadTablePreset
    {
        return LeadTablePreset::Assign;
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
                                $leadType = $this->selectedLeadType();
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
                $this->leadPoolTableSection(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $this->configureLeadPoolTable($table);
    }

    public function updatedReleaseData(): void
    {
        $this->resetSelectedLeadsTable();
        $this->refreshCount(app(HoldingReleaseService::class));
    }

    public function release(HoldingReleaseService $releaseService): void
    {
        $filter = $this->buildFilter();
        $release = $this->releaseForm->getState();
        $maxCount = $this->maxCountFromValue($release['max_count'] ?? null);

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

    protected function leadPoolPreviewMaxCount(): ?int
    {
        return $this->maxCountFromValue($this->releaseData['max_count'] ?? null);
    }
}
