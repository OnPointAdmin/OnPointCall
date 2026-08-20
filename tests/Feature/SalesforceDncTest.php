<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Jobs\DncPushJob;
use App\Jobs\SalesforceDncPushJob;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Leads\DispositionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SalesforceDncTest extends TestCase
{
    use RefreshDatabase;

    public function test_dnc_disposition_dispatches_salesforce_and_dnc_com_jobs(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $lead = $this->makeLead($company);

        app(DispositionService::class)->apply($lead, $user, Disposition::Dnc);

        Queue::assertPushed(DncPushJob::class, fn (DncPushJob $job): bool => $job->leadId === $lead->id);
        Queue::assertPushed(
            SalesforceDncPushJob::class,
            fn (SalesforceDncPushJob $job): bool => $job->leadId === $lead->id && $job->actorId === $user->id,
        );
    }

    public function test_agent_dnc_inserts_salesforce_record_and_still_pushes_dnc_com(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 14:00:00', 'America/New_York'));

        $this->configureSalesforce();
        $this->configureDnc();

        Http::fake([
            '*/services/oauth2/token' => Http::response([
                'access_token' => 'sf-token',
                'expires_in' => 3600,
            ]),
            '*/services/data/v64.0/sobjects/DNC__c*' => Http::response([
                'id' => 'a0N000000000001AAA',
                'success' => true,
                'errors' => [],
            ], 201),
            'www.dncscrub.com/app/main/rpc/pdnc' => Http::response('', 200),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $lead = $this->makeLead($company, [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone_2' => '4045559002',
        ]);

        app(DispositionService::class)->apply(
            $lead,
            $user,
            Disposition::Dnc,
            note: 'Call Center DNC request',
        );

        Http::assertSent(function ($request) use ($lead): bool {
            if (! str_contains($request->url(), '/services/data/v64.0/sobjects/DNC__c')) {
                return false;
            }

            $data = $request->data();

            return ($data['DNC_Reason__c'] ?? null) === 'Customer Requested'
                && ($data['Phone__c'] ?? null) === $lead->phone
                && ($data['First_Name__c'] ?? null) === 'Jane'
                && ($data['Last_Name__c'] ?? null) === 'Doe'
                && ($data['Email__c'] ?? null) === 'jane@example.com'
                && ($data['Request_Source__c'] ?? null) === 'Phone'
                && ($data['Request_Notes__c'] ?? null) === 'Call Center DNC request'
                && ($data['Requested_Date__c'] ?? null) === '2026-08-20'
                && ! array_key_exists('Name', $data)
                && ! array_key_exists('Lead__c', $data)
                && ! array_key_exists('Lead_Marked_as_Internal_DNC__c', $data)
                && ! array_key_exists('Number_added_to_DNC_com__c', $data);
        });

        Http::assertSent(function ($request) use ($lead): bool {
            if (! str_contains($request->url(), '/pdnc')) {
                return false;
            }

            $body = $request->body();

            return str_contains($body, 'actionType=add')
                && str_contains($body, $lead->phone)
                && str_contains($body, (string) $lead->phone_2);
        });

        $history = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::DncPush)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('a0N000000000001AAA', $history->payload['salesforce_id'] ?? null);
        $this->assertArrayNotHasKey('salesforce_error', $history->payload ?? []);
        $this->assertContains($lead->phone, $history->payload['phones'] ?? []);
        $this->assertContains((string) $lead->phone_2, $history->payload['phones'] ?? []);
    }

    public function test_manager_dnc_uses_management_requested_reason(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 14:00:00', 'America/New_York'));

        $this->configureSalesforce();

        Http::fake([
            '*/services/oauth2/token' => Http::response([
                'access_token' => 'sf-token',
                'expires_in' => 3600,
            ]),
            '*/services/data/v64.0/sobjects/DNC__c*' => Http::response([
                'id' => 'a0N000000000002AAA',
                'success' => true,
                'errors' => [],
            ], 201),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Manager,
        ]);
        $lead = $this->makeLead($company, [
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        app(DispositionService::class)->apply($lead, $user, Disposition::Dnc);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/services/data/v64.0/sobjects/DNC__c')) {
                return false;
            }

            $data = $request->data();

            return ($data['DNC_Reason__c'] ?? null) === 'Management Requested'
                && ($data['Request_Source__c'] ?? null) === 'Phone'
                && ! array_key_exists('Email__c', $data)
                && ! array_key_exists('Request_Notes__c', $data);
        });
    }

    public function test_salesforce_insert_error_is_recorded_on_dnc_push_payload(): void
    {
        $this->configureSalesforce();

        Http::fake([
            '*/services/oauth2/token' => Http::response([
                'access_token' => 'sf-token',
                'expires_in' => 3600,
            ]),
            '*/services/data/v64.0/sobjects/DNC__c*' => Http::response([
                [
                    'message' => 'insufficient access rights on object id',
                    'errorCode' => 'INSUFFICIENT_ACCESS',
                    'fields' => [],
                ],
            ], 403),
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $lead = $this->makeLead($company, [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        app(DispositionService::class)->apply($lead, $user, Disposition::Dnc);

        $history = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::DncPush)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertArrayNotHasKey('salesforce_id', $history->payload ?? []);
        $this->assertSame(
            'INSUFFICIENT_ACCESS: insufficient access rights on object id',
            $history->payload['salesforce_error'] ?? null,
        );
    }

    private function configureSalesforce(): void
    {
        config([
            'services.qualification.client_id' => 'sf-client',
            'services.qualification.client_secret' => 'sf-secret',
            'services.qualification.instance_url' => 'https://onpointmrg.my.salesforce.com',
        ]);
    }

    private function configureDnc(): void
    {
        config([
            'services.dnc.login_id' => 'test-login-id',
            'services.dnc.project_id' => 'ONPNT',
            'services.dnc.base_url' => 'https://www.dncscrub.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLead(Company $company, array $overrides = []): Lead
    {
        return Lead::withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'phone' => '4045559001',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ], $overrides));
    }
}
