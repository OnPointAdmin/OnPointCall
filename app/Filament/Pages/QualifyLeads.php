<?php

namespace App\Filament\Pages;

use App\Enums\LeadTablePreset;
use App\Filament\Navigation\QualifyNavigation;
use App\Filament\Pages\Concerns\InteractsWithLeadPoolFilter;
use App\Filament\Resources\QualifyBatches\QualifyBatchResource;
use App\Services\Import\HoldingReleaseService;
use App\Services\Qualify\QualifyLeadsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class QualifyLeads extends Page implements HasTable
{
    use InteractsWithPersistedLeadTable, InteractsWithLeadPoolFilter {
        InteractsWithLeadPoolFilter::updatedTableFilters insteadof InteractsWithPersistedLeadTable;
        InteractsWithLeadPoolFilter::resetTableFiltersForm insteadof InteractsWithPersistedLeadTable;
        InteractsWithLeadPoolFilter::getFilteredSortedTableQuery insteadof InteractsWithPersistedLeadTable;
        InteractsWithLeadPoolFilter::getFilteredTableQuery insteadof InteractsWithPersistedLeadTable;
        InteractsWithLeadPoolFilter::mountInteractsWithTable insteadof InteractsWithPersistedLeadTable;
    }

    protected static string|\UnitEnum|null $navigationGroup = QualifyNavigation::GROUP;

    protected static ?string $navigationParentItem = QualifyNavigation::PARENT;

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Qualify Leads';

    protected static ?string $title = 'Qualify Leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected string $view = 'filament.pages.lead-pool';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $qualifyData = [];

    public function mount(): void
    {
        $this->mountLeadPoolTable();
        $this->refreshCount(app(HoldingReleaseService::class));

        $this->qualifyForm->fill([
            'run_soft_score' => true,
            'run_rnd_check' => true,
            'run_qualification' => true,
            'run_dnc_check' => true,
            'exclude_future_bookings' => true,
            'exclude_past_bookings' => true,
            'max_count' => null,
        ]);
    }

    protected function leadPoolPreset(): LeadTablePreset
    {
        return LeadTablePreset::Qualify;
    }

    public function leadPoolAssignableOnly(): bool
    {
        return false;
    }

    public function qualifyForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Checks')
                    ->schema([
                        Toggle::make('run_soft_score')
                            ->label('Soft Score')
                            ->helperText('Queues a soft-score check per lead. Leads stay unassignable until scored. Already-scored leads are re-checked.')
                            ->default(true)
                            ->live(),
                        Toggle::make('run_rnd_check')
                            ->label('RND')
                            ->helperText('Queues an FCC Reassigned Numbers Database check per lead. Reassigned numbers become Terminal.')
                            ->default(true)
                            ->live(),
                        Toggle::make('run_qualification')
                            ->label('Qualification')
                            ->helperText('Queues partner qualification per lead (Salesforce). If Soft Score is also enabled, Soft Score runs first and its code is sent to Qualification.')
                            ->default(true)
                            ->live(),
                        Toggle::make('run_dnc_check')
                            ->label('DNC')
                            ->helperText('Queues a DNC.com scrub. TCPA / national DNC handling follows each lead’s import batch. Hits are marked DNC.')
                            ->default(true)
                            ->live(),
                        Toggle::make('exclude_future_bookings')
                            ->label('Exclude future bookings')
                            ->helperText('Queries Salesforce Booking__c. Matching future tours mark the lead Booked.')
                            ->default(true)
                            ->live(),
                        Toggle::make('exclude_past_bookings')
                            ->label('Exclude past bookings')
                            ->helperText('Queries Salesforce Booking__c. Matching past tours mark the lead Booked.')
                            ->default(true)
                            ->live(),
                        TextInput::make('max_count')
                            ->label('Max Count')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->nullable()
                            ->live(debounce: 500)
                            ->helperText('Leave empty to qualify all matching leads. Enter a number to qualify that many freshest leads.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('qualifyData');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('qualifyForm')])
                    ->id('qualifyForm')
                    ->livewireSubmitHandler('qualify')
                    ->footer([
                        Actions::make([
                            Action::make('qualify')
                                ->label('Qualify Leads')
                                ->submit('qualify')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalHeading('Run checks on these leads?')
                                ->modalDescription('Selected checks run for matching leads, including leads that already have a result. Holding leads stay unassignable while a check is Pending. RND reassigned numbers become Terminal. DNC hits are marked DNC. Booking hits are marked Booked.'),
                        ]),
                    ]),
                $this->leadPoolTableSection(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $this->configureLeadPoolTable($table);
    }

    public function updatedQualifyData(): void
    {
        $this->resetSelectedLeadsTable();
        $this->refreshCount(app(HoldingReleaseService::class));
    }

    public function qualify(QualifyLeadsService $qualifyLeadsService): void
    {
        $qualify = $this->qualifyForm->getState();
        $runSoftScore = (bool) ($qualify['run_soft_score'] ?? false);
        $runRndCheck = (bool) ($qualify['run_rnd_check'] ?? false);
        $runQualification = (bool) ($qualify['run_qualification'] ?? false);
        $runDncCheck = (bool) ($qualify['run_dnc_check'] ?? false);
        $excludeFutureBookings = (bool) ($qualify['exclude_future_bookings'] ?? false);
        $excludePastBookings = (bool) ($qualify['exclude_past_bookings'] ?? false);

        if (! $runSoftScore && ! $runRndCheck && ! $runQualification && ! $runDncCheck && ! $excludeFutureBookings && ! $excludePastBookings) {
            Notification::make()
                ->title('Select at least one check')
                ->danger()
                ->send();

            return;
        }

        if ($this->holdingCount === 0) {
            Notification::make()
                ->title('No matching leads')
                ->danger()
                ->send();

            return;
        }

        $batch = $qualifyLeadsService->queue(
            companyId: (int) auth()->user()->company_id,
            filter: $this->buildFilter(),
            runSoftScore: $runSoftScore,
            runRndCheck: $runRndCheck,
            runQualification: $runQualification,
            runDncCheck: $runDncCheck,
            excludeFutureBookings: $excludeFutureBookings,
            excludePastBookings: $excludePastBookings,
            maxCount: $this->maxCountFromValue($qualify['max_count'] ?? null),
            userId: auth()->id(),
        );

        Notification::make()
            ->title("Queued {$batch->lead_count} lead(s)")
            ->success()
            ->send();

        $this->redirect(QualifyBatchResource::getUrl('view', ['record' => $batch]));
    }

    protected function leadPoolPreviewMaxCount(): ?int
    {
        return $this->maxCountFromValue($this->qualifyData['max_count'] ?? null);
    }
}
