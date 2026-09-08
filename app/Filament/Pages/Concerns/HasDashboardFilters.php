<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\UserRole;
use App\Filament\Support\LeadTypeSelect;
use App\Models\CallingList;
use App\Models\User;
use App\Services\Dashboard\ManagerDashboardService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

trait HasDashboardFilters
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $filterData = [];

    public string $datePreset = 'today';

    public ?string $runAt = null;

    protected function initializeDashboardFilters(ManagerDashboardService $dashboardService): void
    {
        $companyId = (int) auth()->user()->company_id;
        $timezone = $dashboardService->companyTimezone($companyId);
        $today = Carbon::now($timezone)->toDateString();

        $this->filterForm->fill(array_merge([
            'agent_id' => '',
            'lead_type' => '',
            'calling_list_id' => '',
            'start_date' => $today,
            'end_date' => $today,
        ], $this->extraDashboardFilterDefaults()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraDashboardFilterDefaults(): array
    {
        return [];
    }

    /**
     * @return list<Component>
     */
    protected function extraDashboardFilterFields(): array
    {
        return [];
    }

    public function filterForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema(array_merge([
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
                        Select::make('calling_list_id')
                            ->label('Calling list')
                            ->options(fn (): array => ['' => 'All', 'holding' => 'Holding'] + CallingList::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->nullable()
                            ->placeholder('All'),
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->required(),
                    ], $this->extraDashboardFilterFields()))
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 5,
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
                        ])->alignment(Alignment::End),
                    ])
                    ->extraAttributes(['class' => 'dashboard-filter-form']),
            ]);
    }

    public function applyFiltersAction(ManagerDashboardService $dashboardService): void
    {
        $this->applyDashboardFilters($dashboardService);
    }

    public function applyPreset(string $preset, ManagerDashboardService $dashboardService): void
    {
        $companyId = (int) auth()->user()->company_id;
        $timezone = $dashboardService->companyTimezone($companyId);
        $range = $dashboardService->presetDates($preset, $timezone);

        $this->datePreset = $preset;
        $this->filterForm->fill(array_merge([
            'agent_id' => $this->filterData['agent_id'] ?? '',
            'lead_type' => $this->filterData['lead_type'] ?? '',
            'calling_list_id' => $this->filterData['calling_list_id'] ?? '',
            'start_date' => $range['start']->toDateString(),
            'end_date' => $range['end']->toDateString(),
        ], $this->preservedExtraDashboardFilterValues()));

        $this->applyDashboardFilters($dashboardService);
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
        $this->applyDashboardFilters($dashboardService);
    }

    /**
     * @return array<string, mixed>
     */
    protected function preservedExtraDashboardFilterValues(): array
    {
        $defaults = $this->extraDashboardFilterDefaults();
        $preserved = [];

        foreach (array_keys($defaults) as $key) {
            $preserved[$key] = $this->filterData[$key] ?? $defaults[$key];
        }

        return $preserved;
    }

    /**
     * @return array{
     *     company_id: int,
     *     agent_id: ?int,
     *     lead_type: ?string,
     *     calling_list_id: int|string|null,
     *     range: array{start: Carbon, end: Carbon},
     *     timezone: string,
     *     start_date: string,
     *     end_date: string,
     * }
     */
    protected function parsedDashboardFilters(ManagerDashboardService $dashboardService): array
    {
        $companyId = (int) auth()->user()->company_id;
        $data = $this->filterForm->getState();
        $timezone = $dashboardService->companyTimezone($companyId);

        $startDate = Carbon::parse((string) $data['start_date'], $timezone);
        $endDate = Carbon::parse((string) $data['end_date'], $timezone);

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

        $callingListId = $data['calling_list_id'] ?? '';
        $callingListId = $callingListId === '' || $callingListId === null
            ? null
            : ($callingListId === 'holding' ? 'holding' : (int) $callingListId);

        return array_merge([
            'company_id' => $companyId,
            'agent_id' => $agentId,
            'lead_type' => $leadType,
            'calling_list_id' => $callingListId,
            'range' => $range,
            'timezone' => $timezone,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ], $this->extraParsedDashboardFilters($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extraParsedDashboardFilters(array $data): array
    {
        return [];
    }

    protected function finalizeDashboardFilters(ManagerDashboardService $dashboardService, string $timezone, string $startDate, string $endDate): void
    {
        $this->runAt = Carbon::now($timezone)->format('M j, Y g:i A');
        $this->datePreset = $this->matchingDashboardPreset(
            $dashboardService,
            $timezone,
            $startDate,
            $endDate,
        );
    }

    private function matchingDashboardPreset(
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
