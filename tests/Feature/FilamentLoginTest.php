<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_filament_login_with_seeded_admin_credentials(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->seed(\Database\Seeders\JasonPaineAdminSeeder::class);

        \Livewire\Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm([
                'email' => 'jason.paine@onpointmrg.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs(
            User::where('email', 'jason.paine@onpointmrg.com')->firstOrFail()
        );
    }

    public function test_agent_login_with_seeded_admin_credentials(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->seed(\Database\Seeders\JasonPaineAdminSeeder::class);

        $response = $this->post('/agent/login', [
            'email' => 'jason.paine@onpointmrg.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('agent.workspace'));
    }
}
