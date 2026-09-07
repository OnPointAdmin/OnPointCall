<?php

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Services\Dashboard\ReportScheduleSender;
use Illuminate\Console\Command;

class DashboardEmailDigestCommand extends Command
{
    protected $signature = 'dashboard:email-digest
        {--company= : Company ID to send for}
        {--schedule= : Report schedule ID to send}
        {--force : Skip day, time, and already-sent checks}';

    protected $description = 'Send scheduled dashboard and report emails';

    public function handle(ReportScheduleSender $sender): int
    {
        $query = ReportSchedule::withoutGlobalScopes()->with('recipients');

        if ($this->option('company')) {
            $query->where('company_id', $this->option('company'));
        }

        if ($this->option('schedule')) {
            $query->whereKey($this->option('schedule'));
        } elseif (! $this->option('force')) {
            $query->where('enabled', true);
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->info('No report schedules to send.');

            return self::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            $result = $sender->send($schedule, (bool) $this->option('force'));

            if ($result['sent']) {
                $this->info($result['message']);

                continue;
            }

            if ($result['skipped']) {
                $this->line("Skipped {$schedule->name}: {$result['message']}");

                continue;
            }

            $this->error("Failed {$schedule->name}: {$result['message']}");
        }

        return self::SUCCESS;
    }
}
