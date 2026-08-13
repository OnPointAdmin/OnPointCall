<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Models\Company;
use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportBatchHealthTest extends TestCase
{
    use RefreshDatabase;
    public function test_health_status_is_pending_while_importing(): void
    {
        $batch = $this->batch([
            'status' => ImportBatchStatus::Processing,
        ]);

        $this->assertSame('pending', $batch->healthStatus());
        $this->assertSame('In progress', $batch->healthLabel());
    }

    public function test_health_status_is_pending_while_checks_remain(): void
    {
        $batch = $this->batch([
            'status' => ImportBatchStatus::Completed,
            'run_soft_score' => true,
            'soft_score_pending' => 2,
        ]);

        $this->assertSame('pending', $batch->healthStatus());
    }

    public function test_health_status_is_error_when_soft_score_errors_exist(): void
    {
        $batch = $this->batch([
            'status' => ImportBatchStatus::Completed,
            'run_soft_score' => true,
            'soft_score_error' => 5,
        ]);

        $this->assertSame('error', $batch->healthStatus());
        $this->assertSame('Errors', $batch->healthLabel());
    }

    public function test_health_status_is_ok_when_completed_cleanly(): void
    {
        $batch = $this->batch([
            'status' => ImportBatchStatus::Completed,
            'run_rnd_check' => true,
            'rnd_clear' => 4,
            'rnd_reassigned' => 1,
        ]);

        $this->assertSame('ok', $batch->healthStatus());
        $this->assertSame('Healthy', $batch->healthLabel());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function batch(array $overrides = []): ImportBatch
    {
        $company = Company::factory()->create();

        return ImportBatch::withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'source_filename' => 'test.csv',
            'imported_at' => now(),
            'total_rows' => 5,
            'inserted_count' => 5,
            'duplicate_count' => 0,
            'conflict_count' => 0,
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'run_soft_score' => false,
            'run_rnd_check' => false,
        ], $overrides));
    }
}
