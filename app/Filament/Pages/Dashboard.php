<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\DialableInventory;
use App\Filament\Navigation\DashboardNavigation;
use App\Filament\Pages\Concerns\HasDashboardFilters;
use App\Models\CallingList;
use App\Services\Dashboard\ManagerDashboardService;
use App\Services\Leads\DialableInventoryService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard implements HasActions, HasSchemas
{
    use HasDashboardFilters;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected static string|\UnitEnum|null $navigationGroup = DashboardNavigation::GROUP;

    protected static ?string $navigationParentItem = DashboardNavigation::PARENT_DASHBOARDS;

    protected static ?string $navigationLabel = 'Agent Dashboard';

    protected static ?string $title = 'Agent Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard';

    /**
     * @var array{totals: array<string, array{label: string, count: int, percent: ?float}>, breakdowns: array<string, list<array{kind: string, slug: ?string, label: string, count: int, percent: ?float, items: list<array{kind: string, slug: ?string, label: string, count: int, percent: ?float, items: list<empty>}>}>>, agents: list<array{user_id: int, name: string, metrics: array<string, array{count: int, percent: ?float}>, lists: list<array{calling_list_id: ?int, name: string, metrics: array<string, array{count: int, percent: ?float}>}>>}|null
     */
    public ?array $report = null;

    /**
     * @var array{kind: string, metricKey: string, label: string, dispositionSlug: ?string, reasonLabel: ?string}
     */
    public array $totalsLeadsArguments = [];

    public int $totalsLeadsPage = 1;

    public const TOTALS_LEADS_PER_PAGE = 25;

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

    public function openTotalsLeads(
        string $kind,
        string $metricKey,
        string $label,
        ?string $dispositionSlug = null,
        ?string $reasonLabel = null,
    ): void {
        $this->totalsLeadsArguments = [
            'kind' => $kind,
            'metricKey' => $metricKey,
            'label' => $label,
            'dispositionSlug' => $dispositionSlug,
            'reasonLabel' => $reasonLabel,
        ];
        $this->totalsLeadsPage = 1;
        $this->mountAction('viewTotalsLeads');
    }

    public function gotoTotalsLeadsPage(int $page): void
    {
        $this->totalsLeadsPage = max(1, $page);
    }

    public function viewTotalsLeadsAction(): Action
    {
        return Action::make('viewTotalsLeads')
            ->modalHeading(function (): string {
                $label = $this->totalsLeadsArguments['label'] ?? 'Leads';
                $leadCount = $this->getTotalsLeadsResult()['leads']->total();

                return "{$label} ({$leadCount} ".($leadCount === 1 ? 'lead' : 'leads').')';
            })
            ->modalDescription(function (): ?string {
                $result = $this->getTotalsLeadsResult();
                $eventCount = $result['eventCount'];
                $leadCount = $result['leads']->total();

                if ($eventCount > $leadCount) {
                    return "{$eventCount} matching events in the selected date range.";
                }

                return null;
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth(Width::FourExtraLarge)
            ->modalContent(fn () => view('filament.pages.partials.totals-leads-modal', [
                'leads' => $this->getTotalsLeadsResult()['leads'],
            ]));
    }

    /**
     * @return array{leads: \Illuminate\Contracts\Pagination\LengthAwarePaginator, eventCount: int}
     */
    protected function getTotalsLeadsResult(): array
    {
        $filters = $this->parsedDashboardFilters(app(ManagerDashboardService::class));
        $args = $this->totalsLeadsArguments;

        return app(ManagerDashboardService::class)->leadsForMetric(
            $filters['company_id'],
            $filters['agent_id'],
            $filters['lead_type'],
            $filters['range']['start'],
            $filters['range']['end'],
            $filters['calling_list_id'],
            $args['kind'],
            $args['metricKey'] ?? null,
            $args['dispositionSlug'] ?? null,
            $args['reasonLabel'] ?? null,
            $this->totalsLeadsPage,
            self::TOTALS_LEADS_PER_PAGE,
        );
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
