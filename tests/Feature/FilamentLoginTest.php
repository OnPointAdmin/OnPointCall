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

    public function test_homepage_login_with_seeded_admin_credentials(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->seed(\Database\Seeders\JasonPaineAdminSeeder::class);

        $user = User::where('email', 'jason.paine@onpointmrg.com')->firstOrFail();

        $response = $this->post('/login', [
            'email' => 'jason.paine@onpointmrg.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('choose'));

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertAuthenticatedAs($user, 'agent');

        $this->get('/admin')->assertOk();
    }

    public function test_seeded_admin_can_open_agent_workspace_after_homepage_login(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->seed(\Database\Seeders\JasonPaineAdminSeeder::class);

        $this->post('/login', [
            'email' => 'jason.paine@onpointmrg.com',
            'password' => 'password',
        ])->assertRedirect(route('choose'));

        $this->get(route('agent.workspace'))
            ->assertOk()
            ->assertSee('Get Next Lead');
    }
}
