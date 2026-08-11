<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AllowedEmail;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\ListAssignment;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JasonPaineAdminSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'OnPoint Call Center')->firstOrFail();
        CompanyContext::set($company->id);

        $list = CallingList::query()->where('name', 'Standard')->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['email' => 'jason.paine@onpointcall.com'],
            [
                'company_id' => $company->id,
                'name' => 'Jason Paine',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'active' => true,
                'email_verified_at' => now(),
            ],
        );

        AllowedEmail::query()->updateOrCreate(
            ['company_id' => $company->id, 'email' => 'jason.paine@onpointcall.com'],
            [],
        );

        ListAssignment::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'calling_list_id' => $list->id,
            ],
            [],
        );

        CompanyContext::clear();

        $this->command?->info("Admin ready: {$user->email} (password: password)");
    }
}
