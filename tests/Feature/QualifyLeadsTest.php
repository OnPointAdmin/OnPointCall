<?php

namespace Tests\Feature;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\DncStatus;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\QualifyBatchStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Enums\UserRole;
use App\Filament\Pages\AssignLeads;
use App\Filament\Pages\QualifyLeads;
use App\Filament\Resources\QualifyBatches\QualifyBatchResource;
use App\Jobs\DncScrubJob;
use App\Jobs\QualifyLeadJob;
use App\Jobs\RndLeadJob;
use App\Jobs\SoftScoreLeadJob;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadTypeDefinition;
use App\Models\QualifyBatch;
use App\Models\User;
use App\Services\Qualify\QualifyLeadsService;
use App\Support\CompanyContext;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class QualifyLeadsTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_assign_excludes_unassignable_leads_that_qualify_includes(): void
    {
        [$admin] = $this->setUpQualifyPage();

        $assignable = $this->makeHoldingLead($admin->company_id, '4045551001');
        $dncError = $this->makeHoldingLead($admin->company_id, '4045551002', [
            'dnc_status' => DncStatus::Error,
        ]);
        $rndError = $this->makeHoldingLead($admin->company_id, '4045551003', [
            'rnd_status' => RndStatus::Error,
        ]);

        Livewire::actingAs($admin)
            ->test(AssignLeads::class)
            ->assertSet('holdingCount', 1)
            ->assertCanSeeTableRecords([$assignable])
            ->assertCanNotSeeTableRecords([$dncError, $rndError]);

        Livewire::actingAs($admin)
            ->test(QualifyLeads::class)
            ->assertSet('holdingCount', 3)
            ->assertCanSeeTableRecords([$assignable, $dncError, $rndError]);
    }

    public function test_qualify_page_creates_batch_and_redirects_to_the_view(): void
    {
        Queue::fake();

        [$admin] = $this->setUpQualifyPage();

        $first = $this->makeHoldingLead($admin->company_id, '4045551101', [
            'imported_at' => now()->subDay(),
        ]);
        $second = $this->makeHoldingLead($admin->company_id, '4045551102', [
            'imported_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(QualifyLeads::class)
            ->assertSet('holdingCount', 2)
            ->call('qualify')
            ->assertHasNoFormErrors()
            ->assertRedirect(QualifyBatchResource::getUrl('view', [
                'record' => QualifyBatch::query()->first(),
            ]));

        $batch = QualifyBatch::query()->first();

        $this->assertNotNull($batch);
        $this->assertSame(2, $batch->lead_count);
        $this->assertTrue($batch->run_soft_score);
        $this->assertTrue($batch->run_rnd_check);
        $this->assertTrue($batch->run_qualification);
        $this->assertTrue($batch->run_dnc_check);
        $this->assertSame(QualifyBatchStatus::Processing, $batch->status);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $batch->leads()->pluck('leads.id')->all());
    }

    public function test_unselected_checks_are_not_queued(): void
    {
        Queue::fake();

        [$admin] = $this->setUpQualifyPage();

        $this->makeHoldingLead($admin->company_id, '4045551201');

        Livewire::actingAs($admin)
            ->test(QualifyLeads::class)
            ->fillForm([
                'run_soft_score' => false,
                'run_rnd_check' => true,
                'run_qualification' => false,
                'run_dnc_check' => false,
            ], 'qualifyForm')
            ->call('qualify')
            ->assertHasNoFormErrors();

        $batch = QualifyBatch::query()->first();

        $this->assertTrue($batch->run_rnd_check);
        $this->assertFalse($batch->run_soft_score);
        $this->assertFalse($batch->run_qualification);
        $this->assertFalse($batch->run_dnc_check);
        $this->assertSame(1, $batch->rnd_pending);
        $this->assertSame(0, $batch->soft_score_pending);
        $this->assertSame(0, $batch->qualification_pending);
        $this->assertSame(0, $batch->dnc_pending);

        Queue::assertPushed(RndLeadJob::class, 1);
        Queue::assertNotPushed(SoftScoreLeadJob::class);
        Queue::assertNotPushed(QualifyLeadJob::class);
        Queue::assertNotPushed(DncScrubJob::class);
    }

    public function test_forced_soft_score_is_queued_for_already_complete_leads_and_pending_is_skipped(): void
    {
        Queue::fake();

        [$admin] = $this->setUpQualifyPage();

        $complete = $this->makeHoldingLead($admin->company_id, '4045551301', [
            'soft_score_status' => SoftScoreStatus::Complete,
            'soft_score_code' => 'A1',
        ]);
        $pending = $this->makeHoldingLead($admin->company_id, '4045551302', [
            'soft_score_status' => SoftScoreStatus::Pending,
        ]);

        app(QualifyLeadsService::class)->queue(
            companyId: $admin->company_id,
            filter: new HoldingFilter(leadType: 'standard'),
            runSoftScore: true,
            runRndCheck: false,
            runQualification: false,
            runDncCheck: false,
            excludeFutureBookings: false,
            excludePastBookings: false,
            maxCount: null,
            userId: $admin->id,
        );

        Queue::assertPushed(SoftScoreLeadJob::class, 1);
        Queue::assertPushed(
            SoftScoreLeadJob::class,
            fn (SoftScoreLeadJob $job): bool => $job->leadId === $complete->id
                && $job->force === true
                && $job->qualifyBatchId !== null,
        );
        Queue::assertNotPushed(
            SoftScoreLeadJob::class,
            fn (SoftScoreLeadJob $job): bool => $job->leadId === $pending->id,
        );

        $batch = QualifyBatch::query()->first();
        $this->assertSame(1, $batch->lead_count);
        $this->assertSame(1, $batch->soft_score_pending);
        $this->assertTrue($batch->leads()->where('leads.id', $complete->id)->exists());
        $this->assertFalse($batch->leads()->where('leads.id', $pending->id)->exists());
    }

    public function test_soft_score_and_qualification_are_chained(): void
    {
        Queue::fake();

        [$admin] = $this->setUpQualifyPage();

        $lead = $this->makeHoldingLead($admin->company_id, '4045551401');

        app(QualifyLeadsService::class)->queue(
            companyId: $admin->company_id,
            filter: new HoldingFilter(leadType: 'standard'),
            runSoftScore: true,
            runRndCheck: false,
            runQualification: true,
            runDncCheck: false,
            excludeFutureBookings: false,
            excludePastBookings: false,
            maxCount: null,
            userId: $admin->id,
        );

        Queue::assertPushed(
            SoftScoreLeadJob::class,
            fn (SoftScoreLeadJob $job): bool => $job->leadId === $lead->id
                && $job->dispatchQualificationAfter === true
                && $job->force === true,
        );
        Queue::assertNotPushed(QualifyLeadJob::class);
    }

    public function test_qualify_leads_uses_vertical_dropdown_filters(): void
    {
        [$admin] = $this->setUpQualifyPage();

        $component = Livewire::actingAs($admin)
            ->test(QualifyLeads::class)
            ->assertOk()
            ->assertTableFilterExists('status')
            ->assertTableFilterExists('qualified_partners')
            ->assertTableFilterExists('created_at')
            ->assertTableFilterExists('tour_location');

        $table = $component->instance()->getTable();

        $this->assertSame(FiltersLayout::Dropdown, $table->getFiltersLayout());
        $this->assertSame(1, $table->getFiltersFormColumns());
    }

    /**
     * @return array{0: User}
     */
    private function setUpQualifyPage(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);

        CompanyContext::set($company->id);

        LeadTypeDefinition::createFromName('Standard', 'standard');

        return [$admin];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeHoldingLead(int $companyId, string $phone, array $overrides = []): Lead
    {
        return Lead::withoutGlobalScopes()->create(array_merge([
            'company_id' => $companyId,
            'phone' => $phone,
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'qualification_status' => QualificationStatus::Qualified,
            'imported_at' => now(),
        ], $overrides));
    }
}
