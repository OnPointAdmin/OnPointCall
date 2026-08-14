<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Support\LeadDemographicOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDemographicOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_canonical_age_ranges_and_imported_values(): void
    {
        $company = Company::factory()->create();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045556001',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'age_range' => '30 - 59',
            'imported_at' => now(),
        ]);

        $options = LeadDemographicOptions::for('age_range', $company->id);

        $this->assertSame(LeadDemographicOptions::AGE_RANGES, array_slice($options, 0, count(LeadDemographicOptions::AGE_RANGES)));
        $this->assertContains('30 - 59', $options);
    }

    public function test_includes_current_value_even_if_not_already_stored_elsewhere(): void
    {
        $company = Company::factory()->create();

        $options = LeadDemographicOptions::for('age_range', $company->id, '30 - 59');

        $this->assertContains('30 - 59', $options);
    }

    public function test_includes_imported_income_marital_gender_and_homeowner_values(): void
    {
        $company = Company::factory()->create();

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045556002',
            'status' => LeadStatus::Holding,
            'lead_type' => 'standard',
            'annual_income' => '$80,000 - $90,000',
            'marital_status' => 'Widowed',
            'gender' => 'Non-binary',
            'home_owner' => 'Renter',
            'imported_at' => now(),
        ]);

        $this->assertContains('$80,000 - $90,000', LeadDemographicOptions::for('annual_income', $company->id));
        $this->assertContains('Widowed', LeadDemographicOptions::for('marital_status', $company->id));
        $this->assertContains('Non-binary', LeadDemographicOptions::for('gender', $company->id));
        $this->assertContains('Renter', LeadDemographicOptions::for('home_owner', $company->id));

        $this->assertSame(
            LeadDemographicOptions::INCOMES,
            array_slice(
                LeadDemographicOptions::for('annual_income', $company->id),
                0,
                count(LeadDemographicOptions::INCOMES),
            ),
        );
    }
}
