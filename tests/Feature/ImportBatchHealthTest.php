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

    public function test_health_status_is_pending_while_qualification_checks_remain(): void
    {
        $batch = $this->batch([
            'status' => ImportBatchStatus::Completed,
            'run_qualification' => true,
            'qualification_pending' => 2,
        ]);

        $this->assertSame('pending', $batch->healthStatus());
    }

    public function test_health_status_is_pending_while_dnc_checks_remain(): void
    {
        $batch = $this->batch([
            'status' => ImportBatchStatus::Completed,
            'run_dnc_check' => true,
            'dnc_pending' => 2,
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

    public function test_health_scope_matches_health_status(): void
    {
        $company = Company::factory()->create();

        $batches = collect([
            $this->batch([
                'company_id' => $company->id,
                'status' => ImportBatchStatus::Processing,
            ]),
            $this->batch([
                'company_id' => $company->id,
                'status' => ImportBatchStatus::Completed,
                'run_soft_score' => true,
                'soft_score_pending' => 2,
            ]),
            $this->batch([
                'company_id' => $company->id,
                'status' => ImportBatchStatus::Completed,
                'run_soft_score' => true,
                'soft_score_error' => 5,
            ]),
            $this->batch([
                'company_id' => $company->id,
                'status' => ImportBatchStatus::Completed,
                'run_rnd_check' => true,
                'rnd_clear' => 4,
                'rnd_reassigned' => 1,
            ]),
            $this->batch([
                'company_id' => $company->id,
                'status' => ImportBatchStatus::Failed,
            ]),
            $this->batch([
                'company_id' => $company->id,
                'status' => ImportBatchStatus::Completed,
                'error_message' => 'Import crashed',
            ]),
            $this->batch([
                'company_id' => $company->id,
                'status' => ImportBatchStatus::Completed,
                'run_dnc_check' => true,
                'dnc_pending' => 1,
            ]),
            $this->batch([
                'company_id' => $company->id,
                'status' => ImportBatchStatus::Completed,
                'run_qualification' => true,
                'qualification_error' => 1,
            ]),
            $this->batch([
                'company_id' => $company->id,
                'status' => ImportBatchStatus::Processing,
                'soft_score_error' => 3,
            ]),
        ]);

        foreach (['ok', 'pending', 'error'] as $health) {
            $fromScope = ImportBatch::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->health($health)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $fromPhp = $batches
                ->filter(fn (ImportBatch $batch): bool => $batch->fresh()->healthStatus() === $health)
                ->sortBy('id')
                ->pluck('id')
                ->values()
                ->all();

            $this->assertSame($fromPhp, $fromScope, "health scope {$health} should match healthStatus()");
        }
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
            'run_qualification' => false,
            'run_dnc_check' => false,
        ], $overrides));
    }
}
