<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\DashboardNavigation;
use App\Filament\Pages\Concerns\HasDashboardFilters;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\DispositionDefinition;
use App\Services\Dashboard\CallDetailReportService;
use App\Services\Dashboard\ManagerDashboardService;
use BackedEnum;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CallDetail extends Page implements HasSchemas
{
    use HasDashboardFilters;
    use InteractsWithSchemas;

    protected static string|\UnitEnum|null $navigationGroup = DashboardNavigation::GROUP;

    protected static ?string $navigationParentItem = DashboardNavigation::PARENT_REPORTS;

    protected static ?string $navigationLabel = 'Call Detail';

    protected static ?string $title = 'Call Detail';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected string $view = 'filament.pages.call-detail';

    public int $callsPage = 1;

    public function mount(ManagerDashboardService $dashboardService): void
    {
        $this->initializeDashboardFilters($dashboardService);
        $this->applyDashboardFilters($dashboardService);
    }

    public function getHeading(): string|Htmlable|null
    {
        return '';
    }

    public function gotoCallsPage(int $page): void
    {
        $this->callsPage = max(1, $page);
    }

    public function exportCsv(ManagerDashboardService $dashboardService): StreamedResponse
    {
        $filters = $this->parsedDashboardFilters($dashboardService);
        $reportFilters = $this->reportFilters($filters);
        $csv = app(CallDetailReportService::class)->toCsv(
            $filters['company_id'],
            $reportFilters,
            $filters['range']['start'],
            $filters['range']['end'],
        );

        $filename = sprintf(
            'call-detail-%s-to-%s.csv',
            $filters['start_date'],
            $filters['end_date'],
        );

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function callRows(): LengthAwarePaginator
    {
        $filters = $this->parsedDashboardFilters(app(ManagerDashboardService::class));

        return app(CallDetailReportService::class)->paginate(
            $filters['company_id'],
            $this->reportFilters($filters),
            $filters['range']['start'],
            $filters['range']['end'],
            $this->callsPage,
        );
    }

    public function leadUrl(int $leadId): string
    {
        return LeadResource::getUrl('view', ['record' => $leadId]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraDashboardFilterDefaults(): array
    {
        return [
            'dispositions' => [],
        ];
    }

    /**
     * @return list<Component>
     */
    protected function extraDashboardFilterFields(): array
    {
        return [
            Select::make('dispositions')
                ->label('Dispositions')
                ->options(fn (): array => DispositionDefinition::filterOptions(
                    (int) auth()->user()->company_id,
                ))
                ->multiple()
                ->searchable()
                ->nullable()
                ->placeholder('All')
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extraParsedDashboardFilters(array $data): array
    {
        return [
            'dispositions' => collect($data['dispositions'] ?? [])
                ->map(fn (mixed $slug): string => is_string($slug) ? trim($slug) : '')
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function applyDashboardFilters(ManagerDashboardService $dashboardService): void
    {
        $filters = $this->parsedDashboardFilters($dashboardService);
        $this->callsPage = 1;

        $this->finalizeDashboardFilters(
            $dashboardService,
            $filters['timezone'],
            $filters['start_date'],
            $filters['end_date'],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     agent_id: ?int,
     *     lead_type: ?string,
     *     calling_list_id: int|string|null,
     *     dispositions: list<string>
     * }
     */
    private function reportFilters(array $filters): array
    {
        return [
            'agent_id' => $filters['agent_id'] ?? null,
            'lead_type' => $filters['lead_type'] ?? null,
            'calling_list_id' => $filters['calling_list_id'] ?? null,
            'dispositions' => $filters['dispositions'] ?? [],
        ];
    }
}
