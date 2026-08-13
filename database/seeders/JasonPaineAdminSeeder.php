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
        $company = Company::query()->where('name', 'OnPoint Call Center')->first()
            ?? Company::query()->firstOrFail();
        CompanyContext::set($company->id);

        $list = CallingList::query()->where('name', 'Standard')->first()
            ?? CallingList::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Standard'],
                ['lead_type' => 'standard', 'active' => true],
            );

        $user = User::query()->firstOrNew(['email' => 'jason.paine@onpointmrg.com']);
        $user->fill([
            'company_id' => $company->id,
            'name' => 'Jason Paine',
            'role' => UserRole::Admin,
            'active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        if (! $user->password || ! Hash::check('password', $user->password)) {
            $user->password = 'password';
        }

        $user->save();

        AllowedEmail::query()->updateOrCreate(
            ['company_id' => $company->id, 'email' => 'jason.paine@onpointmrg.com'],
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
