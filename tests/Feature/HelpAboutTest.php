<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\HelpAbout;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpAboutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_help_about_page(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get('/admin/help/about')
            ->assertOk();

        Livewire::actingAs($admin)
            ->test(HelpAbout::class)
            ->assertOk()
            ->assertSee('OnPoint Marketing’s Lead Booking Application')
            ->assertSee('never places phone calls');
    }

    public function test_manager_can_view_help_about_page(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)
            ->get('/admin/help/about')
            ->assertOk();

        Livewire::actingAs($manager)
            ->test(HelpAbout::class)
            ->assertOk()
            ->assertSee('OnPoint Marketing’s Lead Booking Application');
    }

    public function test_agent_cannot_open_admin_help_about_page(): void
    {
        $agent = $this->makeAgent();

        $this->actingAs($agent)
            ->get('/admin/help/about')
            ->assertForbidden();
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

    private function makeManager(): User
    {
        $company = Company::factory()->create();

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Manager,
            'active' => true,
        ]);
    }

    private function makeAgent(): User
    {
        $company = Company::factory()->create();

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
        ]);
    }
}
