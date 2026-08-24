<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Models\Company;
use App\Support\CompanyTimezone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_back_to_eastern_when_settings_are_missing(): void
    {
        $company = Company::factory()->create();

        $this->assertSame('America/New_York', CompanyTimezone::for($company->id));
        $this->assertSame('America/New_York', CompanyTimezone::for(null));
    }

    public function test_reads_app_setting_timezone(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/Los_Angeles',
        ]);

        $this->assertSame('America/Los_Angeles', CompanyTimezone::for($company->id));
    }

    public function test_parses_naive_datetime_in_company_timezone(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/Los_Angeles',
        ]);

        $parsed = CompanyTimezone::parse('2026-08-10T16:00', $company->id);

        $this->assertTrue($parsed->equalTo(Carbon::parse('2026-08-10 16:00:00', 'America/Los_Angeles')));
        $this->assertSame('UTC', $parsed->timezoneName);
    }

    public function test_display_converts_utc_instant_into_company_timezone(): void
    {
        $company = Company::factory()->create();

        AppSetting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'max_attempts' => 6,
            'claim_ttl_minutes' => 20,
            'dashboard_email_timezone' => 'America/Los_Angeles',
        ]);

        $at = Carbon::parse('2026-08-10 18:00:00', 'UTC');

        $this->assertSame('Aug 10, 11:00 AM PDT', CompanyTimezone::display($at, $company->id));
        $this->assertSame('Aug 10, 2026', CompanyTimezone::display($at, $company->id, 'M j, Y'));
    }
}
