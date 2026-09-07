<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportScheduleType: string implements HasLabel
{
    case AgentDashboard = 'agent_dashboard';
    case LeadDashboard = 'lead_dashboard';
    case LeadSource = 'lead_source';

    public function getLabel(): string
    {
        return match ($this) {
            self::AgentDashboard => 'Agent Dashboard',
            self::LeadDashboard => 'Lead Dashboard',
            self::LeadSource => 'Performance by Lead Source',
        };
    }

    public function usesPeriod(): bool
    {
        return $this !== self::LeadDashboard;
    }
}
