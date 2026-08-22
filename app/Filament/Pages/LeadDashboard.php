<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\LeadDashboardSnapshot;
use App\Enums\LeadStatus;
use App\Services\Dashboard\LeadDashboardService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class LeadDashboard extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Dashboard';

    protected static ?string $navigationLabel = 'Lead Dashboard';

    protected static ?string $title = 'Lead Dashboard';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.pages.lead-dashboard';

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return '';
    }

    public function snapshot(): LeadDashboardSnapshot
    {
        $companyId = (int) auth()->user()->company_id;

        return app(LeadDashboardService::class)->snapshot($companyId);
    }

    public function refreshSnapshot(): void
    {
        // Re-render; snapshot() is computed on each request.
    }

    public function statusLabel(string $status): string
    {
        return LeadStatus::tryFrom($status)?->label() ?? $status;
    }
}
