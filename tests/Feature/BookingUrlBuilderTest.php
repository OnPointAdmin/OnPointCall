<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Leads\BookingUrlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingUrlBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_url_from_list_template_and_param_map(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => 'https://form.example.com/book',
            'booking_param_map' => ['id' => 'external_lead_id'],
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'active' => true,
            'booking_url_template' => 'https://form.example.com/tnb',
            'booking_param_map' => ['lead_id' => 'external_lead_id'],
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551234',
            'external_lead_id' => 'CRM-99',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead);

        $this->assertSame('https://form.example.com/tnb?lead_id=CRM-99', $url);
    }

    public function test_falls_back_to_lead_id_when_param_map_empty(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => 'https://form.example.com/book',
            'booking_param_map' => [],
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551234',
            'external_lead_id' => 'CRM-42',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead);

        $this->assertSame('https://form.example.com/book?id=CRM-42', $url);
    }
}
