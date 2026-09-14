<?php

namespace Tests\Feature;

use App\Enums\BookingCheckStatus;
use App\Enums\DncStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ImportBatches\Pages\ViewImportBatch;
use App\Filament\Resources\ImportBatches\RelationManagers\LeadsRelationManager;
use App\Filament\Support\BatchCheckErrorField;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ImportBatchCheckErrorDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicking_check_columns_shows_that_checks_error(): void
    {
        [$batch, $lead, $admin] = $this->makeBatchWithErrors();

        $component = Livewire::actingAs($admin)
            ->test(LeadsRelationManager::class, [
                'ownerRecord' => $batch,
                'pageClass' => ViewImportBatch::class,
            ])
            ->assertOk()
            ->assertCanSeeTableRecords([$lead])
            ->assertSee('RND timeout from provider')
            ->assertSee('Salesforce ID is missing');

        $component
            ->mountTableAction('viewRndResult', $lead)
            ->assertSee('RND timeout from provider');

        $component
            ->mountTableAction('viewQualificationResult', $lead)
            ->assertSee('Salesforce ID is missing');

        $component
            ->mountTableAction('viewDncResult', $lead)
            ->assertSee('DNC.com credentials are invalid');

        $component
            ->mountTableAction('viewBookingCheckResult', $lead)
            ->assertSee('Salesforce booking query failed');

        $component
            ->mountTableAction('viewSoftScoreResult', $lead)
            ->assertSee('Soft Score credentials missing');

        $component
            ->mountTableAction('viewLeadCheckErrors', $lead)
            ->assertSee('RND timeout from provider')
            ->assertSee('Salesforce ID is missing')
            ->assertSee('DNC.com credentials are invalid')
            ->assertSee('Salesforce booking query failed')
            ->assertSee('Soft Score credentials missing');
    }

    public function test_viewing_batch_error_counts_shows_grouped_messages(): void
    {
        [$batch, , $admin] = $this->makeBatchWithErrors();

        $this->makeLead($batch, [
            'phone' => '4045559002',
            'rnd_status' => RndStatus::Error,
            'rnd_last_error' => 'RND timeout from provider',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(ViewImportBatch::class, ['record' => $batch->getRouteKey()])
            ->assertOk()
            ->assertFormComponentActionVisible('rnd_error', 'viewRndError')
            ->mountFormComponentAction('rnd_error', 'viewRndError');

        $this->assertNotNull($component->instance()->getMountedAction());
        $this->assertSame('viewRndError', $component->instance()->getMountedAction()->getName());
        $this->assertStringContainsString(
            'RND timeout from provider',
            $this->mountedActionModalHtml($component),
        );
        $this->assertStringContainsString('2 leads', $this->mountedActionModalHtml($component));

        $component
            ->unmountFormComponentAction()
            ->assertFormComponentActionVisible('qualification_error', 'viewQualificationError')
            ->mountFormComponentAction('qualification_error', 'viewQualificationError');

        $this->assertStringContainsString(
            'Salesforce ID is missing',
            $this->mountedActionModalHtml($component),
        );
        $this->assertStringContainsString('1 lead', $this->mountedActionModalHtml($component));
    }

    public function test_grouped_errors_count_unique_messages(): void
    {
        [$batch] = $this->makeBatchWithErrors();

        $this->makeLead($batch, [
            'phone' => '4045559002',
            'rnd_status' => RndStatus::Error,
            'rnd_last_error' => 'RND timeout from provider',
        ]);
        $this->makeLead($batch, [
            'phone' => '4045559003',
            'rnd_status' => RndStatus::Error,
            'rnd_last_error' => null,
        ]);

        $grouped = BatchCheckErrorField::grouped($batch, 'rnd_status', 'rnd_last_error');

        $this->assertSame([
            ['message' => 'RND timeout from provider', 'total' => 2],
            ['message' => 'No error message was stored.', 'total' => 1],
        ], $grouped);
    }

    /**
     * @return array{0: ImportBatch, 1: Lead, 2: User}
     */
    private function makeBatchWithErrors(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);
        CompanyContext::set($company->id);

        $batch = ImportBatch::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_filename' => 'errors.csv',
            'imported_at' => now(),
            'total_rows' => 1,
            'inserted_count' => 1,
            'lead_type' => 'standard',
            'status' => ImportBatchStatus::Completed,
            'run_soft_score' => true,
            'run_rnd_check' => true,
            'run_qualification' => true,
            'run_dnc_check' => true,
            'exclude_future_bookings' => true,
            'soft_score_error' => 1,
            'rnd_error' => 1,
            'qualification_error' => 1,
            'dnc_error' => 1,
            'booking_check_error' => 1,
        ]);

        $lead = $this->makeLead($batch, [
            'soft_score_status' => SoftScoreStatus::Error,
            'soft_score_last_error' => 'Soft Score credentials missing',
            'rnd_status' => RndStatus::Error,
            'rnd_last_error' => 'RND timeout from provider',
            'qualification_status' => QualificationStatus::Error,
            'qualification_last_error' => 'Salesforce ID is missing',
            'dnc_status' => DncStatus::Error,
            'dnc_last_error' => 'DNC.com credentials are invalid',
            'booking_check_status' => BookingCheckStatus::Error,
            'booking_check_last_error' => 'Salesforce booking query failed',
        ]);

        return [$batch, $lead, $admin];
    }

    private function mountedActionModalHtml(Testable $component): string
    {
        $content = $component->instance()->getMountedAction()?->getModalContent();

        if ($content instanceof View) {
            return $content->render();
        }

        if ($content instanceof Htmlable) {
            return $content->toHtml();
        }

        return (string) $content;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLead(ImportBatch $batch, array $overrides = []): Lead
    {
        return Lead::withoutGlobalScopes()->create(array_merge([
            'company_id' => $batch->company_id,
            'import_batch_id' => $batch->id,
            'phone' => '4045559001',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ], $overrides));
    }
}
