<?php

namespace Tests\Unit;

use App\Enums\DncStatus;
use App\Enums\ImportBatchStatus;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Services\Dnc\DncBatchBreakdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DncBatchBreakdownServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_blocking_hits_by_reason(): void
    {
        $company = Company::factory()->create();

        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'test.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'run_dnc_check' => true,
            'dnc_hit' => 3,
        ]);

        $this->makeLead($company->id, $batch->id, DncStatus::Hit, ['hit_reason' => 'litigator']);
        $this->makeLead($company->id, $batch->id, DncStatus::Hit, ['hit_reason' => 'idnc']);
        $this->makeLead($company->id, $batch->id, DncStatus::Hit, ['hit_reason' => 'national']);

        $breakdown = app(DncBatchBreakdownService::class)->forImportBatch($batch->fresh());

        $this->assertSame(1, $breakdown->litigator);
        $this->assertSame(1, $breakdown->internal);
        $this->assertSame(1, $breakdown->national);
        $this->assertSame(0, $breakdown->state);
        $this->assertSame(0, $breakdown->dnc);
        $this->assertSame(3, $breakdown->blockingHits());
    }

    public function test_it_counts_ignored_national_and_state_with_consent(): void
    {
        $company = Company::factory()->create();

        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'test.csv',
            'imported_at' => now(),
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'run_dnc_check' => true,
            'ignore_national_dnc' => true,
        ]);

        $this->makeLead($company->id, $batch->id, DncStatus::Clear, [
            'ignored_reasons' => ['national'],
            'ignore_national_dnc' => true,
        ]);
        $this->makeLead($company->id, $batch->id, DncStatus::Clear, [
            'ignored_reasons' => ['national', 'state'],
            'ignore_national_dnc' => true,
        ]);

        $breakdown = app(DncBatchBreakdownService::class)->forImportBatch($batch->fresh());

        $this->assertSame(2, $breakdown->nationalIgnored);
        $this->assertSame(1, $breakdown->stateIgnored);
        $this->assertSame(3, $breakdown->ignoredHits());
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function makeLead(int $companyId, int $batchId, DncStatus $status, array $result): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'import_batch_id' => $batchId,
            'phone' => '404555'.random_int(1000, 9999),
            'lead_type' => 'standard',
            'imported_at' => now(),
            'dnc_status' => $status,
            'dnc_result' => $result,
        ]);
    }
}
