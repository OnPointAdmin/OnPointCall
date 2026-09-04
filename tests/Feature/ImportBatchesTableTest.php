<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ImportBatches\Pages\ListImportBatches;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\LeadTypeDefinition;
use App\Models\User;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImportBatchesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_filter_by_status_lead_type_and_health(): void
    {
        [$company, $admin] = $this->makeAdminCompany();
        CompanyContext::set($company->id);
        LeadTypeDefinition::createFromName('Standard', 'standard');
        LeadTypeDefinition::createFromName('TNB', 'tnb');

        $completedStandard = $this->makeBatch($company->id, [
            'source_filename' => 'standard-ok.csv',
            'status' => ImportBatchStatus::Completed,
            'lead_type' => 'standard',
        ]);
        $failedStandard = $this->makeBatch($company->id, [
            'source_filename' => 'standard-failed.csv',
            'status' => ImportBatchStatus::Failed,
            'lead_type' => 'standard',
        ]);
        $pendingTnb = $this->makeBatch($company->id, [
            'source_filename' => 'tnb-pending.csv',
            'status' => ImportBatchStatus::Processing,
            'lead_type' => 'tnb',
        ]);
        $errorTnb = $this->makeBatch($company->id, [
            'source_filename' => 'tnb-errors.csv',
            'status' => ImportBatchStatus::Completed,
            'lead_type' => 'tnb',
            'run_soft_score' => true,
            'soft_score_error' => 2,
        ]);

        Livewire::actingAs($admin)
            ->test(ListImportBatches::class)
            ->assertCanSeeTableRecords([$completedStandard, $failedStandard, $pendingTnb, $errorTnb])
            ->filterTable('status', ImportBatchStatus::Failed->value)
            ->assertCanSeeTableRecords([$failedStandard])
            ->assertCanNotSeeTableRecords([$completedStandard, $pendingTnb, $errorTnb])
            ->removeTableFilter('status')
            ->filterTable('lead_type', 'tnb')
            ->assertCanSeeTableRecords([$pendingTnb, $errorTnb])
            ->assertCanNotSeeTableRecords([$completedStandard, $failedStandard])
            ->removeTableFilter('lead_type')
            ->filterTable('health', 'error')
            ->assertCanSeeTableRecords([$failedStandard, $errorTnb])
            ->assertCanNotSeeTableRecords([$completedStandard, $pendingTnb])
            ->filterTable('health', 'pending')
            ->assertCanSeeTableRecords([$pendingTnb])
            ->assertCanNotSeeTableRecords([$completedStandard, $failedStandard, $errorTnb])
            ->filterTable('health', 'ok')
            ->assertCanSeeTableRecords([$completedStandard])
            ->assertCanNotSeeTableRecords([$failedStandard, $pendingTnb, $errorTnb]);
    }

    public function test_list_can_filter_by_imported_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 15:00:00', 'America/New_York')->utc());

        [$company, $admin] = $this->makeAdminCompany();
        CompanyContext::set($company->id);

        $today = $this->makeBatch($company->id, [
            'source_filename' => 'today.csv',
            'imported_at' => Carbon::parse('2026-09-04 10:00:00', 'America/New_York')->utc(),
        ]);
        $yesterday = $this->makeBatch($company->id, [
            'source_filename' => 'yesterday.csv',
            'imported_at' => Carbon::parse('2026-09-03 10:00:00', 'America/New_York')->utc(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListImportBatches::class)
            ->assertCanSeeTableRecords([$today, $yesterday])
            ->filterTable('imported_at', [
                'start_date' => '2026-09-04',
                'end_date' => '2026-09-04',
            ])
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$yesterday]);
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function makeAdminCompany(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        return [$company, $admin];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeBatch(int $companyId, array $overrides = []): ImportBatch
    {
        return ImportBatch::withoutGlobalScopes()->create(array_merge([
            'company_id' => $companyId,
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
