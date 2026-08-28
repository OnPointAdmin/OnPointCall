<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\Import\ImportBatchCheckRetryService;
use Illuminate\Console\Command;

class ReapplyDncConsentCommand extends Command
{
    protected $signature = 'dnc:reapply-consent {--batch=* : Import batch ID} {--dry-run : Count what would change without writing}';

    protected $description = 'Turn on TCPA consent (ignore national and state DNC) for opted-in batches and re-apply stored scrub results';

    public function handle(ImportBatchCheckRetryService $retryService): int
    {
        $batchIds = array_values(array_filter(array_map('intval', (array) $this->option('batch'))));
        $dryRun = (bool) $this->option('dry-run');

        $query = ImportBatch::withoutGlobalScopes()->where('run_dnc_check', true);

        if ($batchIds !== []) {
            $query->whereIn('id', $batchIds);
        }

        $batches = $query->orderBy('id')->get();

        if ($batches->isEmpty()) {
            $this->warn('No matching import batches.');

            return self::SUCCESS;
        }

        $totals = ['released' => 0, 'remaining_hits' => 0, 'skipped' => 0];

        foreach ($batches as $batch) {
            if ($dryRun) {
                $this->line("Batch {$batch->id} ({$batch->source_filename}): {$batch->dnc_hit} current DNC hit(s), ignore_national=".($batch->ignore_national_dnc ? 'yes' : 'no'));

                continue;
            }

            $result = $retryService->reapplyDncConsentPolicy($batch);
            $totals['released'] += $result['released'];
            $totals['remaining_hits'] += $result['remaining_hits'];
            $totals['skipped'] += $result['skipped'];

            $this->info("Batch {$batch->id}: released {$result['released']}, remaining hits {$result['remaining_hits']}, skipped {$result['skipped']}");
        }

        if (! $dryRun) {
            $this->info("Total released {$totals['released']}; remaining DNC hits {$totals['remaining_hits']}; skipped {$totals['skipped']}.");
        }

        return self::SUCCESS;
    }
}
