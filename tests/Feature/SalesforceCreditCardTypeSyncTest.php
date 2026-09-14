<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Salesforce\SalesforceCreditCardTypeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalesforceCreditCardTypeSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_updates_blank_leads_by_external_lead_id(): void
    {
        $this->configureSalesforce();

        $company = Company::factory()->create();
        $lead = $this->makeLead($company, [
            'phone' => '4045558001',
            'external_lead_id' => '00QVr00000cZW3xMAG',
        ]);

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    [
                        'Id' => '00QVr00000cZW3xMAG',
                        'Type_of_Credit_Card__c' => 'Visa',
                    ],
                ],
                'done' => true,
            ]),
        ]);

        $result = app(SalesforceCreditCardTypeSyncService::class)->sync();

        $this->assertSame(1, $result['updated']);
        $this->assertSame('Visa', $lead->fresh()->credit_card_type);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'query')
                && str_contains(urldecode($request->url()), 'Type_of_Credit_Card__c')
                && str_contains(urldecode($request->url()), '00QVr00000cZW3xMAG');
        });
    }

    public function test_sync_skips_filled_rows_unless_forced(): void
    {
        $this->configureSalesforce();

        $company = Company::factory()->create();
        $filled = $this->makeLead($company, [
            'phone' => '4045558002',
            'external_lead_id' => '00Q000000000002AAA',
            'credit_card_type' => 'Visa',
        ]);
        $blank = $this->makeLead($company, [
            'phone' => '4045558003',
            'external_lead_id' => '00Q000000000003AAA',
        ]);

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    [
                        'Id' => '00Q000000000002AAA',
                        'Type_of_Credit_Card__c' => 'Mastercard',
                    ],
                    [
                        'Id' => '00Q000000000003AAA',
                        'Type_of_Credit_Card__c' => 'Discover',
                    ],
                ],
                'done' => true,
            ]),
        ]);

        app(SalesforceCreditCardTypeSyncService::class)->sync();

        $this->assertSame('Visa', $filled->fresh()->credit_card_type);
        $this->assertSame('Discover', $blank->fresh()->credit_card_type);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'query')) {
                return false;
            }

            $soql = urldecode($request->url());

            return str_contains($soql, '00Q000000000003AAA')
                && ! str_contains($soql, '00Q000000000002AAA');
        });
    }

    public function test_sync_force_overwrites_filled_rows(): void
    {
        $this->configureSalesforce();

        $company = Company::factory()->create();
        $filled = $this->makeLead($company, [
            'phone' => '4045558002',
            'external_lead_id' => '00Q000000000002AAA',
            'credit_card_type' => 'Visa',
        ]);

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    [
                        'Id' => '00Q000000000002AAA',
                        'Type_of_Credit_Card__c' => 'Mastercard',
                    ],
                ],
                'done' => true,
            ]),
        ]);

        app(SalesforceCreditCardTypeSyncService::class)->sync(force: true);

        $this->assertSame('Mastercard', $filled->fresh()->credit_card_type);
    }

    public function test_dry_run_does_not_write(): void
    {
        $this->configureSalesforce();

        $company = Company::factory()->create();
        $lead = $this->makeLead($company, [
            'phone' => '4045558004',
            'external_lead_id' => '00Q000000000004AAA',
        ]);

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    [
                        'Id' => '00Q000000000004AAA',
                        'Type_of_Credit_Card__c' => 'Amex',
                    ],
                ],
                'done' => true,
            ]),
        ]);

        $this->artisan('salesforce:sync-credit-card-type', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertNull($lead->fresh()->credit_card_type);
    }

    public function test_sync_skips_ids_that_are_not_salesforce_leads(): void
    {
        $this->configureSalesforce();

        $company = Company::factory()->create();
        $lead = $this->makeLead($company, [
            'phone' => '4045558005',
            'external_lead_id' => 'OP-1001',
        ]);

        Http::fake();

        $result = app(SalesforceCreditCardTypeSyncService::class)->sync();

        $this->assertSame(1, $result['skipped_not_lead_id']);
        $this->assertSame(0, $result['queried']);
        $this->assertNull($lead->fresh()->credit_card_type);
        Http::assertNothingSent();
    }

    public function test_command_scopes_to_company(): void
    {
        $this->configureSalesforce();

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $leadA = $this->makeLead($companyA, [
            'phone' => '4045558006',
            'external_lead_id' => '00Q000000000005AAA',
        ]);
        $leadB = $this->makeLead($companyB, [
            'phone' => '4045558007',
            'external_lead_id' => '00Q000000000006AAA',
        ]);

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => [
                    [
                        'Id' => '00Q000000000005AAA',
                        'Type_of_Credit_Card__c' => 'Visa',
                    ],
                    [
                        'Id' => '00Q000000000006AAA',
                        'Type_of_Credit_Card__c' => 'Mastercard',
                    ],
                ],
                'done' => true,
            ]),
        ]);

        $this->artisan('salesforce:sync-credit-card-type', ['--company' => (string) $companyA->id])
            ->assertSuccessful();

        $this->assertSame('Visa', $leadA->fresh()->credit_card_type);
        $this->assertNull($leadB->fresh()->credit_card_type);
    }

    public function test_sync_explains_when_lead_object_is_not_accessible(): void
    {
        $this->configureSalesforce();

        $company = Company::factory()->create();
        $this->makeLead($company, [
            'phone' => '4045558008',
            'external_lead_id' => '00Q000000000007AAA',
        ]);

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                [
                    'errorCode' => 'INVALID_TYPE',
                    'message' => "sObject type 'Lead' is not supported.",
                ],
            ], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot query Lead');

        app(SalesforceCreditCardTypeSyncService::class)->sync();
    }

    private function configureSalesforce(): void
    {
        config([
            'services.qualification.client_id' => 'client',
            'services.qualification.client_secret' => 'secret',
            'services.qualification.instance_url' => 'https://example.salesforce.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLead(Company $company, array $overrides): Lead
    {
        return Lead::withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ], $overrides));
    }
}
