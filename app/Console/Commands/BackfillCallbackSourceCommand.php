<?php

namespace App\Console\Commands;

use App\Services\Leads\BookingCallbackSyncService;
use Illuminate\Console\Command;

class BackfillCallbackSourceCommand extends Command
{
    protected $signature = 'salesforce:backfill-callback-source
                            {--dry-run : Show what would change without writing}
                            {--company= : Limit to a company ID}';

    protected $description = 'Backfill venue and event on callback leads from Salesforce Booking__c';

    public function handle(BookingCallbackSyncService $sync): int
    {
        $company = $this->option('company');
        $companyId = is_string($company) && $company !== '' ? (int) $company : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = $sync->backfillSourceFields($companyId, $dryRun);

        if ($result['failed']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — no rows written.');
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned', $result['scanned']],
                [$dryRun ? 'Would update' : 'Updated', $result['updated']],
                ['Unchanged', $result['unchanged']],
                ['Booking not found in Salesforce', $result['not_found']],
            ],
        );

        return self::SUCCESS;
    }
}
