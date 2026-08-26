<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Leads\BookingUrlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class BookingUrlBuilderTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

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

        $list = $this->createCallingList($company->id, overrides: [
            'name' => 'Standard',
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

    public function test_builds_url_when_param_map_was_saved_as_json_string(): void
    {
        $company = Company::factory()->create();

        $settings = AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => 'https://peoplereally.win/data/i_opma_call.html',
            'booking_param_map' => ['id' => 'external_lead_id'],
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        DB::table('app_settings')->where('id', $settings->id)->update([
            'booking_param_map' => json_encode(json_encode(['2ff7-7114-0d49' => 'external_lead_id'])),
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

        $url = app(BookingUrlBuilder::class)->build($lead->fresh());

        $this->assertSame(
            'https://peoplereally.win/data/i_opma_call.html?2ff7-7114-0d49=CRM-42',
            $url,
        );
    }

    public function test_uses_internal_id_when_mapped_external_id_is_missing(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => 'https://peoplereally.win/data/i_opma_call.html',
            'booking_param_map' => ['2ff7-7114-0d49' => 'external_lead_id'],
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $list = $this->createCallingList($company->id, overrides: [
            'name' => 'Standard',
            'booking_param_map' => [],
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '3528746129',
            'first_name' => 'Jake',
            'last_name' => 'Eagle',
            'state' => 'FL',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead);

        $this->assertSame(
            'https://peoplereally.win/data/i_opma_call.html?2ff7-7114-0d49='.$lead->id,
            $url,
        );
    }
}
