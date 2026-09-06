<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\DashboardNavigation;
use App\Filament\Pages\Concerns\HasDashboardFilters;
use App\Services\Dashboard\LeadSourceReportService;
use App\Services\Dashboard\ManagerDashboardService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class PerformanceByLeadSource extends Page implements HasSchemas
{
    use HasDashboardFilters;
    use InteractsWithSchemas;

    protected static string|\UnitEnum|null $navigationGroup = DashboardNavigation::GROUP;

    protected static ?string $navigationParentItem = DashboardNavigation::PARENT_REPORTS;

    protected static ?string $navigationLabel = 'Performance by Lead Source';

    protected static ?string $title = 'Performance by Lead Source';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected string $view = 'filament.pages.performance-by-lead-source';

    /**
     * @var array{totals: array{total_leads_called: int, booked: int, booked_percent: ?float}, rows: list<array{venue: string, event: string, total_leads_called: int, booked: int, booked_percent: ?float}>}|null
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

    public function formatPercent(?float $percent): string
    {
        return app(LeadSourceReportService::class)->formatPercent($percent);
    }

    private function applyDashboardFilters(ManagerDashboardService $dashboardService): void
    {
        $filters = $this->parsedDashboardFilters($dashboardService);

        $this->report = app(LeadSourceReportService::class)->report(
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
