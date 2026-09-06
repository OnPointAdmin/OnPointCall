<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\DialableInventory;
use App\Filament\Navigation\DashboardNavigation;
use App\Filament\Pages\Concerns\HasDashboardFilters;
use App\Models\CallingList;
use App\Services\Dashboard\ManagerDashboardService;
use App\Services\Leads\DialableInventoryService;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard implements HasSchemas
{
    use HasDashboardFilters;
    use InteractsWithSchemas;

    protected static string|\UnitEnum|null $navigationGroup = DashboardNavigation::GROUP;

    protected static ?string $navigationParentItem = DashboardNavigation::PARENT_DASHBOARDS;

    protected static ?string $navigationLabel = 'Agent Dashboard';

    protected static ?string $title = 'Agent Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard';

    /**
     * @var array{totals: array<string, array{label: string, count: int, percent: ?float}>, agents: list<array{user_id: int, name: string, metrics: array<string, array{count: int, percent: ?float}>, lists: list<array{calling_list_id: ?int, name: string, metrics: array<string, array{count: int, percent: ?float}>}>}>}|null
     */
    public ?array $report = null;

    public function mount(ManagerDashboardService $dashboardService): void
    {
        $this->initializeDashboardFilters($dashboardService);
        $this->applyDashboardFilters($dashboardService);
    }

    public function getHeading(): string|Htmlable|null
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

    /**
     * @return list<array{list: CallingList, inventory: DialableInventory}>
     */
    public function queueStatuses(): array
    {
        $companyId = (int) auth()->user()->company_id;

        return app(DialableInventoryService::class)->activeTodayForCompany($companyId);
    }

    private function applyDashboardFilters(ManagerDashboardService $dashboardService): void
    {
        $filters = $this->parsedDashboardFilters($dashboardService);

        $this->report = $dashboardService->report(
            $filters['company_id'],
            $filters['agent_id'],
            $filters['lead_type'],
            $filters['range']['start'],
            $filters['range']['end'],
            $filters['calling_list_id'],
        );

        $this->finalizeDashboardFilters(
            $dashboardService,
            $filters['timezone'],
            $filters['start_date'],
            $filters['end_date'],
        );
    }
}
