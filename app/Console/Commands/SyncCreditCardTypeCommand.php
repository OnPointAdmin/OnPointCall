<?php

namespace App\Console\Commands;

use App\Services\Salesforce\SalesforceCreditCardTypeSyncService;
use Illuminate\Console\Command;

class SyncCreditCardTypeCommand extends Command
{
    protected $signature = 'salesforce:sync-credit-card-type
                            {--dry-run : Show what would change without writing}
                            {--force : Overwrite existing credit_card_type values}
                            {--company= : Limit to a company ID}';

    protected $description = 'Backfill leads.credit_card_type from Salesforce Type_of_Credit_Card__c';

    public function handle(SalesforceCreditCardTypeSyncService $sync): int
    {
        if (! $sync->isConfigured()) {
            $this->error('Salesforce credentials are not configured.');

            return self::FAILURE;
        }

        $company = $this->option('company');
        $companyId = is_string($company) && $company !== '' ? (int) $company : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = $sync->sync(
            companyId: $companyId,
            dryRun: $dryRun,
            force: (bool) $this->option('force'),
        );

        if ($dryRun) {
            $this->warn('Dry run — no rows written.');
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned', $result['scanned']],
                ['Skipped (not a Lead Id)', $result['skipped_not_lead_id']],
                ['Queried Salesforce', $result['queried']],
                [$dryRun ? 'Would update' : 'Updated', $result['updated']],
                ['Unchanged', $result['unchanged']],
                ['Not found in Salesforce', $result['not_found']],
            ],
        );

        return self::SUCCESS;
    }
}
