<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CompanySeeder::class);

        $company = Company::query()->where('name', 'OnPoint Call Center')->firstOrFail();

        CompanyContext::set($company->id);

        $this->callWith(StateRuleSeeder::class, ['companyId' => $company->id]);
        $this->callWith(LeadTypeSeeder::class, ['companyId' => $company->id]);
        $this->callWith(CallingListSeeder::class, ['companyId' => $company->id]);
        $this->callWith(AppSettingSeeder::class, ['companyId' => $company->id]);
        $this->callWith(ImportMappingSeeder::class, ['companyId' => $company->id]);
        $this->call(JasonPaineAdminSeeder::class);

        CompanyContext::clear();
    }
}
