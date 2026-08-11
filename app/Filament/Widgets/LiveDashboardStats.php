<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\ManagerDashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class LiveDashboardStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $stats = app(ManagerDashboardService::class)->todayStats(Auth::user()->company_id);

        return [
            Stat::make('Bookings Today', $stats['bookings'])
                ->color('success'),
            Stat::make('Calls Today', $stats['calls']),
            Stat::make('Skips Today', $stats['skips'])
                ->color('warning'),
            Stat::make('Overdue Callbacks', $stats['overdue_callbacks'])
                ->color($stats['overdue_callbacks'] > 0 ? 'danger' : 'gray'),
            Stat::make('Orphaned Callbacks', $stats['orphaned_callbacks'])
                ->color($stats['orphaned_callbacks'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
