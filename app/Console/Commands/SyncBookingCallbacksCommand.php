<?php

namespace App\Console\Commands;

use App\Jobs\SyncBookingCallbacksJob;
use App\Models\Company;
use App\Services\Leads\BookingCallbackScheduleRunner;
use App\Services\Salesforce\SalesforceBookingCallbackClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SyncBookingCallbacksCommand extends Command
{
    protected $signature = 'salesforce:sync-booking-callbacks
                            {--dry-run : Show what would change without writing}
                            {--company= : Limit to a company ID}
                            {--list= : Import into this calling list ID}
                            {--scheduled : Run only when a company schedule matches the current minute}';

    protected $description = 'Pull Salesforce Callback bookings into the Agent Callbacks list';

    public function handle(
        SalesforceBookingCallbackClient $client,
        BookingCallbackScheduleRunner $runner,
    ): int {
        if ($this->option('scheduled')) {
            $result = $runner->run();
            $this->info("Ran {$result['ran']} booking callback schedule(s); skipped {$result['skipped']}.");

            return self::SUCCESS;
        }

        if (! $client->isConfigured()) {
            $this->error('Salesforce credentials are not configured.');

            return self::FAILURE;
        }

        $company = $this->option('company');
        $companyIds = is_string($company) && $company !== ''
            ? [(int) $company]
            : Company::query()->orderBy('id')->pluck('id')->all();

        if ($companyIds === []) {
            $this->warn('No companies to sync.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $list = $this->option('list');
        $callingListId = is_string($list) && $list !== '' ? (int) $list : null;
        $failed = false;

        foreach ($companyIds as $companyId) {
            if ($dryRun) {
                $this->warn('Dry run — no rows written.');
            }

            $result = Bus::dispatchNow(new SyncBookingCallbacksJob(
                $companyId,
                $dryRun,
                'command',
                $callingListId,
            ));
            $this->report((int) $companyId, $result, $dryRun);

            if (! empty($result['refused']) || ! empty($result['failed'])) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(int $companyId, array $result, bool $dryRun): void
    {
        if (! empty($result['refused']) || ! empty($result['failed'])) {
            $this->error('Company '.$companyId.': '.($result['message'] ?? 'Import failed.'));

            return;
        }

        $this->info('Company '.$companyId);

        $this->table(
            ['Metric', 'Count'],
            [
                [$dryRun ? 'Would create' : 'Created', $result['created']],
                [$dryRun ? 'Would update' : 'Updated', $result['updated']],
                [$dryRun ? 'Would refresh source' : 'Source refreshed', $result['source_refreshed'] ?? 0],
                ['Skipped (no phone)', $result['skipped_no_phone']],
                ['Skipped (DNC / booked / terminal)', $result['skipped_dnc_terminal']],
                ['Closed', $result['closed']],
                ['Agent match errors', $result['agent_match_errors']],
                ['Errors', $result['error_count']],
            ],
        );
    }
}
