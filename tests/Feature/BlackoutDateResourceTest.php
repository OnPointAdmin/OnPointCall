<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\BlackoutDates\Pages\CreateBlackoutDate;
use App\Models\BlackoutDate;
use App\Models\Company;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\UsStates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlackoutDateResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_state_scoped_blackouts_for_same_date(): void
    {
        $admin = $this->makeAdmin();

        CompanyContext::clear();

        Livewire::actingAs($admin)
            ->test(CreateBlackoutDate::class)
            ->fillForm([
                'date' => '2026-12-25',
                'state_code' => 'CA',
                'label' => 'Christmas',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::actingAs($admin)
            ->test(CreateBlackoutDate::class)
            ->fillForm([
                'date' => '2026-12-25',
                'state_code' => 'NY',
                'label' => 'Christmas',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(BlackoutDate::withoutGlobalScopes()
            ->where('company_id', $admin->company_id)
            ->where('state_code', 'CA')
            ->whereDate('date', '2026-12-25')
            ->exists());

        $this->assertTrue(BlackoutDate::withoutGlobalScopes()
            ->where('company_id', $admin->company_id)
            ->where('state_code', 'NY')
            ->whereDate('date', '2026-12-25')
            ->exists());
    }

    public function test_duplicate_state_and_date_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        BlackoutDate::withoutGlobalScopes()->create([
            'company_id' => $admin->company_id,
            'date' => '2026-12-25',
            'state_code' => 'CA',
            'label' => 'Christmas',
        ]);

        CompanyContext::clear();

        Livewire::actingAs($admin)
            ->test(CreateBlackoutDate::class)
            ->fillForm([
                'date' => '2026-12-25',
                'state_code' => 'CA',
                'label' => 'Duplicate',
            ])
            ->call('create')
            ->assertHasFormErrors(['date']);
    }

    public function test_all_states_blackout_defaults_state_code(): void
    {
        $admin = $this->makeAdmin();

        CompanyContext::clear();

        Livewire::actingAs($admin)
            ->test(CreateBlackoutDate::class)
            ->fillForm([
                'date' => '2026-01-01',
                'state_code' => UsStates::ALL,
                'label' => 'New Year',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(BlackoutDate::withoutGlobalScopes()
            ->where('company_id', $admin->company_id)
            ->where('state_code', UsStates::ALL)
            ->whereDate('date', '2026-01-01')
            ->exists());
    }

    public function test_description_is_required(): void
    {
        $admin = $this->makeAdmin();

        CompanyContext::clear();

        Livewire::actingAs($admin)
            ->test(CreateBlackoutDate::class)
            ->fillForm([
                'date' => '2026-09-07',
                'state_code' => UsStates::ALL,
            ])
            ->call('create')
            ->assertHasFormErrors(['label' => 'required']);
    }

    private function makeAdmin(): User
    {
        $company = Company::factory()->create();

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);
    }
}
