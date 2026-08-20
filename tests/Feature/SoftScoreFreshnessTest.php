<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\SoftScoreStatus;
use App\Enums\UserRole;
use App\Jobs\SoftScoreLeadJob;
use App\Livewire\Agent\Workspace;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\ImportMapping;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\ListAssignment;
use App\Models\User;
use App\Services\Import\LeadImportService;
use App\Services\SoftScore\SoftScoreClient;
use App\Services\SoftScore\SoftScoreService;
use App\Support\CompanyContext;
use Database\Seeders\ImportMappingSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class SoftScoreFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_skips_soft_score_when_code_and_checked_at_are_recent(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name,Score,CheckedAt',
            '4045556001,Jane,A,'.now()->subDays(5)->toDateString(),
        ]);

        $path = storage_path('app/imports/soft-score-fresh.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'soft-score-fresh.csv', 'standard', true);

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
            'soft_score_code' => 'Score',
            'soft_score_checked_at' => 'CheckedAt',
        ], 'standard');

        CompanyContext::clear();

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045556001')->first();

        $this->assertNotNull($lead);
        $this->assertSame(SoftScoreStatus::Recent, $lead->soft_score_status);
        $this->assertSame('A', $lead->soft_score_code);
        $this->assertNotNull($lead->soft_score_checked_at);
        $this->assertSame(0, (int) $batch->fresh()->soft_score_pending);
        $this->assertSame(1, (int) $batch->fresh()->soft_score_qualified);
        $this->assertSame(0, (int) $batch->fresh()->soft_score_error);
        Queue::assertNotPushed(SoftScoreLeadJob::class);
    }

    public function test_import_runs_soft_score_when_code_is_blank_even_if_checked_at_is_recent(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name,CheckedAt',
            '4045556002,Jane,'.now()->subDays(2)->toDateString(),
        ]);

        $path = storage_path('app/imports/soft-score-blank-code.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'soft-score-blank-code.csv', 'standard', true);

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
            'soft_score_checked_at' => 'CheckedAt',
        ], 'standard');

        CompanyContext::clear();

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045556002')->first();

        $this->assertNotNull($lead);
        $this->assertSame(SoftScoreStatus::Pending, $lead->soft_score_status);
        Queue::assertPushed(SoftScoreLeadJob::class, 1);
    }

    public function test_import_runs_soft_score_when_checked_at_is_missing(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name,Score',
            '4045556003,Jane,B',
        ]);

        $path = storage_path('app/imports/soft-score-missing-date.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'soft-score-missing-date.csv', 'standard', true);

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
            'soft_score_code' => 'Score',
        ], 'standard');

        CompanyContext::clear();

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045556003')->first();

        $this->assertNotNull($lead);
        $this->assertSame(SoftScoreStatus::Pending, $lead->soft_score_status);
        Queue::assertPushed(SoftScoreLeadJob::class, 1);
    }

    public function test_import_runs_soft_score_when_checked_at_is_older_than_freshness_window(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        $csv = implode("\n", [
            'Phone,First Name,Score,CheckedAt',
            '4045556004,Jane,C,'.now()->subDays(45)->toDateString(),
        ]);

        $path = storage_path('app/imports/soft-score-stale.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $service = app(LeadImportService::class);
        $batch = $service->createBatch($company->id, 'soft-score-stale.csv', 'standard', true);

        $service->process($batch, $path, [
            'phone' => 'Phone',
            'first_name' => 'First Name',
            'soft_score_code' => 'Score',
            'soft_score_checked_at' => 'CheckedAt',
        ], 'standard');

        CompanyContext::clear();

        $lead = Lead::withoutGlobalScopes()->where('phone', '4045556004')->first();

        $this->assertNotNull($lead);
        $this->assertSame(SoftScoreStatus::Pending, $lead->soft_score_status);
        Queue::assertPushed(SoftScoreLeadJob::class, 1);
    }

    public function test_ssis_seeder_maps_soft_score_checked_at_to_original_lead_submit_date(): void
    {
        $company = Company::factory()->create();
        CompanyContext::set($company->id);

        (new ImportMappingSeeder)->run($company->id);

        $ssis = ImportMapping::query()->where('name', 'SSIS')->first();

        CompanyContext::clear();

        $this->assertNotNull($ssis);
        $this->assertSame('original_lead_submit_date', $ssis->column_map['soft_score_checked_at'] ?? null);
    }

    public function test_migration_backfills_soft_score_checked_at_on_existing_import_mappings(): void
    {
        $company = Company::factory()->create();

        $mapping = ImportMapping::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Legacy SSIS',
            'lead_type' => 'standard',
            'is_default' => false,
            'column_map' => [
                'phone' => 'caller_id',
                'original_lead_submit_date' => 'original_lead_submit_date',
            ],
        ]);

        $migration = require database_path('migrations/2026_08_13_000027_add_soft_score_checked_at_to_import_mappings.php');
        $migration->up();

        $mapping->refresh();

        $this->assertSame(
            'original_lead_submit_date',
            $mapping->column_map['soft_score_checked_at'] ?? null,
        );
    }

    public function test_score_lead_early_exits_without_calling_client_when_fresh(): void
    {
        $company = Company::factory()->create();

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045556005',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
            'soft_score_code' => 'A',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_checked_at' => now()->subDays(3),
        ]);

        $client = Mockery::mock(SoftScoreClient::class);
        $client->shouldNotReceive('scoreLead');
        $this->app->instance(SoftScoreClient::class, $client);

        app(SoftScoreService::class)->scoreLead($lead);

        $lead->refresh();

        $this->assertSame(SoftScoreStatus::Complete, $lead->soft_score_status);
        $this->assertSame('A', $lead->soft_score_code);
        $this->assertNull($lead->soft_score_last_error);
    }

    public function test_agent_run_soft_score_shows_modal_when_fresh(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'active' => true,
        ]);

        ListAssignment::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'calling_list_id' => $list->id,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045556006',
            'first_name' => 'Pat',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
            'soft_score_code' => 'B',
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_checked_at' => now()->subDays(1),
            'qualification_status' => \App\Enums\QualificationStatus::Qualified,
            'qualification_checked_at' => now()->subDays(1),
        ]);

        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        $client = Mockery::mock(SoftScoreClient::class);
        $client->shouldNotReceive('scoreLead');
        $this->app->instance(SoftScoreClient::class, $client);

        $this->actingAs($user, 'agent');

        Livewire::test(Workspace::class)
            ->assertDontSee('Run Soft Score')
            ->call('runSoftScore')
            ->assertSet('showSoftScoreRecentModal', false)
            ->assertSet('softScoreMessage', '');

        $lead->refresh();
        $this->assertSame('B', $lead->soft_score_code);
        $this->assertSame(SoftScoreStatus::Complete, $lead->soft_score_status);
    }
}
