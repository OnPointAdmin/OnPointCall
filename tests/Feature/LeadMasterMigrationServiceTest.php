<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Services\Migration\LeadMasterMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class LeadMasterMigrationServiceTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_only_callbacks_are_assigned_to_list_a_by_lead_type(): void
    {
        $company = Company::factory()->create();
        $cadence = $this->createCadence($company->id);

        $standardList = $this->createCallingList($company->id, $cadence, [
            'name' => 'Standard - List A',
            'lead_type' => 'standard',
        ]);
        $tnbList = $this->createCallingList($company->id, $cadence, [
            'name' => 'TNB - List A',
            'lead_type' => 'tnb',
        ]);

        User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Iranays Ferro',
        ]);

        $csv = implode("\n", [
            'Lead ID,Lead Type,Phone,First Name,Last Name,Assigned Agent,Disposition,Status,Callback At,Batch Date',
            'ID-NA,Standard,4045550001,Ann,NoAnswer,Iranays Ferro,No Answer,In Progress,,8/18/2026',
            'ID-HOLD,Tour No Buy,4045550002,Hank,Hold,,,(empty),,',
            'ID-CB-STD,Standard,4045550003,Cara,StandardCb,Iranays Ferro,Callback,In Progress,,8/18/2026',
            'ID-CB-TNB,Tour No Buy,4045550004,Tom,TnbCb,Iranays Ferro,Callback,In Progress,,8/18/2026',
        ]);
        $csv = str_replace('(empty)', '', $csv);

        $path = storage_path('app/imports/leadmaster-list-a-test.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $stats = app(LeadMasterMigrationService::class)->migrate($company, $path);

        $this->assertSame(4, $stats['inserted']);
        $this->assertSame(2, $stats['status_counts'][LeadStatus::Holding->value]);
        $this->assertSame(2, $stats['status_counts'][LeadStatus::Callback->value]);
        $this->assertArrayNotHasKey(LeadStatus::Callable->value, $stats['status_counts']);

        $noAnswer = Lead::withoutGlobalScopes()->where('phone', '4045550001')->first();
        $this->assertSame(LeadStatus::Holding, $noAnswer->status);
        $this->assertNull($noAnswer->calling_list_id);

        $standardCb = Lead::withoutGlobalScopes()->where('phone', '4045550003')->first();
        $this->assertSame(LeadStatus::Callback, $standardCb->status);
        $this->assertSame($standardList->id, $standardCb->calling_list_id);

        $tnbCb = Lead::withoutGlobalScopes()->where('phone', '4045550004')->first();
        $this->assertSame(LeadStatus::Callback, $tnbCb->status);
        $this->assertSame($tnbList->id, $tnbCb->calling_list_id);
    }

    public function test_migrate_aborts_when_list_a_is_missing(): void
    {
        $company = Company::factory()->create();
        $this->createCallingList($company->id, overrides: [
            'name' => 'Standard - List A',
            'lead_type' => 'standard',
        ]);

        $csv = implode("\n", [
            'Lead ID,Lead Type,Phone,First Name,Disposition',
            'ID-1,Standard,4045550001,Ann,Callback',
        ]);
        $path = storage_path('app/imports/leadmaster-missing-list-test.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing calling lists required for callbacks: TNB - List A');

        app(LeadMasterMigrationService::class)->migrate($company, $path);
    }

    public function test_maps_lead_id_when_csv_has_utf8_bom(): void
    {
        $company = Company::factory()->create();
        $cadence = $this->createCadence($company->id);
        $this->createCallingList($company->id, $cadence, [
            'name' => 'Standard - List A',
            'lead_type' => 'standard',
        ]);
        $this->createCallingList($company->id, $cadence, [
            'name' => 'TNB - List A',
            'lead_type' => 'tnb',
        ]);

        $csv = "\xEF\xBB\xBFLead ID,Lead Type,Phone,First Name,Last Name,Disposition\n"
            ."00QVr00000yVxNpMAK,Standard,3102480441,Maria,Soto,No Answer\n";

        $path = storage_path('app/imports/leadmaster-bom-test.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        app(LeadMasterMigrationService::class)->migrate($company, $path);

        $lead = Lead::withoutGlobalScopes()->where('phone', '3102480441')->first();

        $this->assertNotNull($lead);
        $this->assertSame('00QVr00000yVxNpMAK', $lead->external_lead_id);
    }

    public function test_backfill_sets_missing_external_lead_ids_by_phone(): void
    {
        $company = Company::factory()->create();

        $blank = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '3102480441',
            'first_name' => 'Maria',
            'last_name' => 'Soto',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $alreadySet = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045550001',
            'external_lead_id' => 'KEEP-ME',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $takenId = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045550002',
            'external_lead_id' => 'TAKEN-ID',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $conflict = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045550003',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $csv = implode("\n", [
            "\xEF\xBB\xBFLead ID,Phone,First Name",
            '00QVr00000yVxNpMAK,3102480441,Maria',
            'SHOULD-NOT-OVERWRITE,4045550001,Ann',
            'TAKEN-ID,4045550003,Conflict',
        ]);

        $path = storage_path('app/imports/leadmaster-backfill-ids.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csv);

        $stats = app(LeadMasterMigrationService::class)->backfillExternalLeadIds($company, $path);

        $this->assertSame(1, $stats['updated']);
        $this->assertSame(1, $stats['already_set']);
        $this->assertSame(1, $stats['skipped_conflict']);
        $this->assertSame('00QVr00000yVxNpMAK', $blank->fresh()->external_lead_id);
        $this->assertSame('KEEP-ME', $alreadySet->fresh()->external_lead_id);
        $this->assertNull($conflict->fresh()->external_lead_id);
        $this->assertSame('TAKEN-ID', $takenId->fresh()->external_lead_id);
    }
}
