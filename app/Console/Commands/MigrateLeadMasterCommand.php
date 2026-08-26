<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Migration\LeadMasterMigrationService;
use App\Support\CompanyContext;
use Illuminate\Console\Command;

class MigrateLeadMasterCommand extends Command
{
    protected $signature = 'leads:migrate-leadmaster
        {file : Path to LeadMaster CSV file}
        {--company= : Company ID}
        {--dry-run : Validate and report without writing}
        {--force : Insert even when phone or Lead ID already exists in the database}
        {--backfill-dates : Update occurred_at on existing leadmaster_migration disposition rows from Batch Date}
        {--backfill-ids : Fill blank external_lead_id values from the Lead ID column, matching by phone}';

    protected $description = 'One-time migration from LeadMaster Google Sheet export';

    public function handle(LeadMasterMigrationService $migration): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        $company = $this->resolveCompany();
        CompanyContext::set($company->id);

        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = ! (bool) $this->option('force');

        if ($this->option('backfill-ids')) {
            try {
                $stats = $migration->backfillExternalLeadIds($company, $path);
            } finally {
                CompanyContext::clear();
            }

            $this->info('LeadMaster external lead ID backfill');
            $this->line("CSV rows: {$stats['csv_rows']}");
            $this->line("Updated: {$stats['updated']}");
            $this->line("Already set: {$stats['already_set']}");
            $this->line("Skipped (no matching lead): {$stats['skipped_missing_lead']}");
            $this->line("Skipped (ID already on another lead): {$stats['skipped_conflict']}");
            $this->line("Skipped (no Lead ID in CSV): {$stats['skipped_no_id']}");
            $this->line("Skipped (invalid phone): {$stats['skipped_invalid_phone']}");

            return self::SUCCESS;
        }

        if ($this->option('backfill-dates')) {
            try {
                $stats = $migration->backfillDispositionDates($company, $path);
            } finally {
                CompanyContext::clear();
            }

            $this->info('LeadMaster disposition date backfill');
            $this->line("Updated: {$stats['updated']}");
            $this->line("No Batch Date in CSV (left unchanged): {$stats['fallback_now']}");
            $this->line("Skipped (missing lead): {$stats['skipped']}");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no database writes.');
        }

        try {
            $stats = $migration->migrate($company, $path, $dryRun, $skipExisting);
        } finally {
            CompanyContext::clear();
        }

        $this->newLine();
        $this->info('LeadMaster migration summary');
        $this->line("Total rows: {$stats['total_rows']}");

        if ($dryRun) {
            $this->line("Would insert: {$stats['would_insert']}");
        } else {
            $this->line("Inserted: {$stats['inserted']}");
        }

        $this->line("Skipped (duplicate): {$stats['skipped_duplicate']}");
        $this->line("Skipped (invalid phone): {$stats['skipped_invalid_phone']}");
        $this->line("Bad dates nulled: {$stats['bad_dates']}");
        $this->line("Invalid Phone 2: {$stats['invalid_phone_2']}");
        $this->line("Callbacks without matched agent: {$stats['callbacks_without_agent']}");

        $this->newLine();
        $this->info('Lead status breakdown');
        foreach ($stats['status_counts'] as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        $this->newLine();
        $this->info('Disposition breakdown');
        foreach ($stats['disposition_counts'] as $disposition => $count) {
            $this->line("  {$disposition}: {$count}");
        }

        $this->newLine();
        $this->info('Lead type breakdown');
        foreach ($stats['lead_type_counts'] as $leadType => $count) {
            $this->line("  {$leadType}: {$count}");
        }

        if ($stats['unmatched_agents'] !== []) {
            $this->newLine();
            $this->warn('Unmatched agent names');
            foreach ($stats['unmatched_agents'] as $name => $count) {
                $this->line("  {$name}: {$count}");
            }
        }

        if (! $dryRun && ($stats['skipped_duplicate'] > 0 || $stats['skipped_invalid_phone'] > 0)) {
            $this->newLine();
            $this->line('Skipped rows written to storage/app/imports/leadmaster-skipped.csv');
        }

        return self::SUCCESS;
    }

    private function resolveCompany(): Company
    {
        if ($companyId = $this->option('company')) {
            return Company::query()->findOrFail($companyId);
        }

        return Company::query()->firstOrFail();
    }
}
