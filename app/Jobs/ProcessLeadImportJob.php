<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Import\LeadImportService;
use App\Support\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessLeadImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $columnMap
     */
    public function __construct(
        public int $batchId,
        public string $storedFilePath,
        public array $columnMap,
    ) {}

    public function handle(LeadImportService $importService): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->findOrFail($this->batchId);

        CompanyContext::set($batch->company_id);

        try {
            $importService->process(
                $batch,
                $this->storedFilePath,
                $this->columnMap,
                $batch->lead_type,
            );
        } catch (Throwable $exception) {
            $importService->markFailed($batch, $exception->getMessage());

            throw $exception;
        } finally {
            CompanyContext::clear();
        }
    }
}
