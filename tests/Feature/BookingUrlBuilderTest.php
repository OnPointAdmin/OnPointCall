<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Lead;
use App\Services\Leads\BookingUrlBuilder;
use App\Support\BookingParamMap;
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
            'external_lead_id' => '00QVr00000cZW3xMAG',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead->fresh());

        $this->assertSame(
            'https://peoplereally.win/data/i_opma_call.html?2ff7-7114-0d49=00QVr00000cZW3xMAG',
            $url,
        );
    }

    public function test_omits_find_entry_when_mapped_external_id_is_missing(): void
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

        $this->assertSame('https://peoplereally.win/data/i_opma_call.html', $url);
    }

    public function test_company_default_map_prefills_formyoula_fields(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => 'https://peoplereally.win/data/i_opma_call.html',
            'booking_param_map' => BookingParamMap::all(),
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $list = $this->createCallingList($company->id, overrides: [
            'name' => 'TNB',
            'lead_type' => 'tnb',
            'booking_param_map' => [],
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045553333',
            'first_name' => 'Sam',
            'last_name' => 'Smith',
            'email' => 'sam@example.com',
            'address' => '100 Main St',
            'address_2' => 'Unit 5',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip' => '30303',
            'age_range' => '35-44',
            'annual_income' => '75000',
            'marital_status' => 'Married',
            'gender' => 'M',
            'home_owner' => 'Yes',
            'soft_score_code' => 'A',
            'external_lead_id' => 'OP-TNB-1',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'tnb',
            'calling_list_id' => $list->id,
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead);

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://peoplereally.win/data/i_opma_call.html?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        $this->assertArrayNotHasKey('2ff7-7114-0d49', $params);
        $this->assertSame('Sam', $params['f776-d580-398a']);
        $this->assertSame('Smith', $params['0a4f-9d93-87f3']);
        $this->assertSame('4045553333', $params['d64f-4a22-9c19']);
        $this->assertSame('sam@example.com', $params['e668-b01b-2857']);
        $this->assertStringContainsString('sam@example.com', $url);
        $this->assertStringNotContainsString('%40', $url);
        $this->assertSame('100 Main St', $params['6a99-2204-4c7c']);
        $this->assertSame('Unit 5', $params['49cc-f3e9-d39e']);
        $this->assertSame('Atlanta', $params['2861-1af5-2ebb']);
        $this->assertSame('GA', $params['d995-a548-5acd']);
        $this->assertSame('30303', $params['ff18-88a3-11df']);
        $this->assertSame('35-44', $params['9fe5-80b8-65d6']);
        $this->assertSame('75000', $params['9edb-f4b6-5970']);
        $this->assertSame('Married', $params['4d26-9cb2-0a61']);
        $this->assertSame('M', $params['fd65-8e57-7f36']);
        $this->assertSame('Yes', $params['24c0-a88e-ff42']);
        $this->assertSame('A', $params['a4e4-0997-7dd4']);
    }

    public function test_email_at_sign_is_not_percent_encoded(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => 'https://peoplereally.win/data/i_opma_call.html',
            'booking_param_map' => ['e668-b01b-2857' => 'email'],
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551234',
            'email' => 'camila.drake@example.com',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead);

        $this->assertSame(
            'https://peoplereally.win/data/i_opma_call.html?e668-b01b-2857=camila.drake@example.com',
            $url,
        );
    }

    public function test_omits_find_entry_when_external_id_is_not_a_salesforce_lead(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => 'https://peoplereally.win/data/i_opma_call.html',
            'booking_param_map' => BookingParamMap::all(),
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '5166956824',
            'first_name' => 'Daniel',
            'last_name' => 'Frankel',
            'email' => 'dbf533@gmail.com',
            'external_lead_id' => 'a1EVr000002gRM1MAM',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'tnb',
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead);

        $this->assertStringNotContainsString('2ff7-7114-0d49=', $url);
        $this->assertStringNotContainsString('a1EVr000002gRM1MAM', $url);
        $this->assertStringContainsString('f776-d580-398a=Daniel', $url);
        $this->assertStringContainsString('dbf533@gmail.com', $url);
    }

    public function test_sends_find_entry_when_external_id_is_a_salesforce_lead(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => 'https://peoplereally.win/data/i_opma_call.html',
            'booking_param_map' => BookingParamMap::all(),
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551234',
            'first_name' => 'Jane',
            'external_lead_id' => '00QVr00000cZW3xMAG',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'standard',
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead);

        $this->assertStringContainsString('2ff7-7114-0d49=00QVr00000cZW3xMAG', $url);
        $this->assertStringContainsString('f776-d580-398a=Jane', $url);
    }

    public function test_sends_find_entry_from_extra_lead_id_when_present(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => BookingParamMap::FORM_URL,
            'booking_param_map' => [
                'f776-d580-398a' => 'first_name',
                'e668-b01b-2857' => 'email',
            ],
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '5166956824',
            'first_name' => 'Daniel',
            'email' => 'dbf533@gmail.com',
            'external_lead_id' => 'a1EVr000002gRM1MAM',
            'extra_fields' => ['LeadId' => '00QVr00000cZW3xMAG'],
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'tnb',
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead);

        $this->assertStringContainsString('2ff7-7114-0d49=00QVr00000cZW3xMAG', $url);
        $this->assertStringNotContainsString('a1EVr000002gRM1MAM', $url);
        $this->assertStringContainsString('f776-d580-398a=Daniel', $url);
    }

    public function test_encodes_plus_in_age_and_income_so_form_radios_match(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'booking_url_template' => 'https://app.formyoula.com/online_v5/69ebf54074d3e900155c1d78',
            'booking_param_map' => [
                '9fe5-80b8-65d6' => 'age_range',
                '9edb-f4b6-5970' => 'annual_income',
                'e668-b01b-2857' => 'email',
            ],
            'max_attempts' => 3,
            'claim_ttl_minutes' => 20,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '5166956824',
            'email' => 'dbf533@gmail.com',
            'age_range' => '60+',
            'annual_income' => '$100K +',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => 'callable',
            'lead_type' => 'tnb',
            'imported_at' => now(),
            'queue_rank' => 1,
        ]);

        $url = app(BookingUrlBuilder::class)->build($lead);

        $this->assertStringContainsString('9fe5-80b8-65d6=60%2B', $url);
        $this->assertStringContainsString('9edb-f4b6-5970=$100K%20%2B', $url);
        $this->assertStringContainsString('dbf533@gmail.com', $url);
        $this->assertStringNotContainsString('60+', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $this->assertSame('60+', $params['9fe5-80b8-65d6']);
        $this->assertSame('$100K +', $params['9edb-f4b6-5970']);
        $this->assertSame('dbf533@gmail.com', $params['e668-b01b-2857']);
    }
}
