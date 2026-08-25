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
}
