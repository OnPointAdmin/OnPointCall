<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Support\LeadTypeSelect;
use App\Models\User;
use App\Services\Dashboard\ManagerDashboardService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected static ?string $navigationLabel = 'Agent Dashboard';

    protected static ?string $title = 'Agent Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filterData = [];

    /**
     * @var array{totals: array<string, array{label: string, count: int, percent: ?float}>, agents: list<array{user_id: int, name: string, metrics: array<string, array{count: int, percent: ?float}>}>}|null
     */
    public ?array $report = null;

    public ?string $runAt = null;

    public string $datePreset = 'today';

    public function mount(ManagerDashboardService $dashboardService): void
    {
        $companyId = (int) auth()->user()->company_id;
        $timezone = $dashboardService->companyTimezone($companyId);
        $today = Carbon::now($timezone)->toDateString();

        $this->filterForm->fill([
            'agent_id' => '',
            'lead_type' => '',
            'start_date' => $today,
            'end_date' => $today,
        ]);

        $this->applyFilters($dashboardService);
    }

    public function filterForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('agent_id')
                            ->label('Rep')
                            ->options(fn (): array => ['' => 'All'] + User::query()
                                ->where('role', UserRole::Agent)
                                ->where('active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()),
                        LeadTypeSelect::make(allowCreate: false)
                            ->required(false)
                            ->nullable()
                            ->placeholder('All'),
                        DatePicker::make('start_date')
                            ->label('Run dates')
                            ->required(),
                        DatePicker::make('end_date')
                            ->hiddenLabel()
                            ->required(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ])
                    ->extraAttributes(['class' => 'dashboard-filter-section']),
            ])
            ->statePath('filterData');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('filterForm')])
                    ->id('dashboardFilterForm')
                    ->livewireSubmitHandler('applyFiltersAction')
                    ->footer([
                        Actions::make([
                            Action::make('applyFilters')
                                ->label('Apply')
                                ->submit('applyFiltersAction')
                                ->extraAttributes(['class' => 'dashboard-apply-btn']),
                        ])->alignment(\Filament\Support\Enums\Alignment::End),
                    ])
                    ->extraAttributes(['class' => 'dashboard-filter-form']),
            ]);
    }

    public function applyFiltersAction(ManagerDashboardService $dashboardService): void
    {
        $this->applyFilters($dashboardService);
    }

    public function applyPreset(string $preset, ManagerDashboardService $dashboardService): void
    {
        $companyId = (int) auth()->user()->company_id;
        $timezone = $dashboardService->companyTimezone($companyId);
        $range = $dashboardService->presetDates($preset, $timezone);

        $this->datePreset = $preset;
        $this->filterForm->fill([
            'agent_id' => $this->filterData['agent_id'] ?? '',
            'lead_type' => $this->filterData['lead_type'] ?? '',
            'start_date' => $range['start']->toDateString(),
            'end_date' => $range['end']->toDateString(),
        ]);

        $this->applyFilters($dashboardService);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function datePresets(): array
    {
        return app(ManagerDashboardService::class)->datePresets();
    }

    public function refreshReport(ManagerDashboardService $dashboardService): void
    {
        $this->applyFilters($dashboardService);
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return '';
    }

    /**
     * @return list<class-string>
     */
    public function getHeaderWidgets(): array
    {
        return [];
    }

    public function dashboardTitle(): string
    {
        $name = trim((string) auth()->user()->name);
        $firstName = $name !== '' ? explode(' ', $name)[0] : 'Manager';

        return "{$firstName} Manager Dashboard";
    }

    /**
     * @return list<class-string>
     */
    public function getWidgets(): array
    {
        return [];
    }

    /**
     * @return list<array{key: string, label: string, show_percent: bool}>
     */
    public function metricDefinitions(): array
    {
        return app(ManagerDashboardService::class)->metricDefinitions();
    }

    public function formatPercent(array $metrics, string $key): string
    {
        return app(ManagerDashboardService::class)->formatPercent($metrics, $key);
    }

    private function applyFilters(ManagerDashboardService $dashboardService): void
    {
        $companyId = (int) auth()->user()->company_id;
        $data = $this->filterForm->getState();

        $startDate = Carbon::parse((string) $data['start_date']);
        $endDate = Carbon::parse((string) $data['end_date']);

        if ($endDate->lessThan($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $range = $dashboardService->dateRange($companyId, $startDate, $endDate);

        $agentId = isset($data['agent_id']) && $data['agent_id'] !== ''
            ? (int) $data['agent_id']
            : null;

        $leadType = isset($data['lead_type']) && $data['lead_type'] !== ''
            ? (string) $data['lead_type']
            : null;

        $this->report = $dashboardService->report(
            $companyId,
            $agentId,
            $leadType,
            $range['start'],
            $range['end'],
        );

        $timezone = $dashboardService->companyTimezone($companyId);
        $this->runAt = Carbon::now($timezone)->format('M j, Y g:i A');
        $this->datePreset = $this->matchingPreset(
            $dashboardService,
            $timezone,
            $startDate->toDateString(),
            $endDate->toDateString(),
        );
    }

    private function matchingPreset(
        ManagerDashboardService $dashboardService,
        string $timezone,
        string $startDate,
        string $endDate,
    ): string {
        foreach ($dashboardService->datePresets() as $preset) {
            $range = $dashboardService->presetDates($preset['key'], $timezone);

            if ($range['start']->toDateString() === $startDate && $range['end']->toDateString() === $endDate) {
                return $preset['key'];
            }
        }

        return 'custom';
    }
}
