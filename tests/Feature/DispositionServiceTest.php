<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Exceptions\CallbackOutsideWindowException;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\StateRule;
use App\Models\User;
use App\Services\Leads\DispositionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispositionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_moves_lead_to_bottom_without_incrementing_attempts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'cadence' => ['day_parts' => ['morning'], 'min_gap_minutes' => 60],
            'active' => true,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552001',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'attempt_count' => 2,
            'queue_rank' => 1,
            'imported_at' => now(),
        ]);

        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552002',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'queue_rank' => 5,
            'imported_at' => now(),
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);

        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        $updated = app(DispositionService::class)->apply(
            $lead,
            $user,
            Disposition::Skip,
            skipReason: 'Busy signal',
        );

        $this->assertSame(2, $updated->attempt_count);
        $this->assertSame(6, $updated->queue_rank);
        $this->assertDatabaseMissing('lead_claims', ['lead_id' => $lead->id]);
    }

    public function test_callback_rejects_time_outside_legal_window(): void
    {
        $company = Company::factory()->create();

        StateRule::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'state_code' => 'NY',
            'window_start' => '08:00:00',
            'window_end' => '21:00:00',
            'permitted_weekdays' => [0, 1, 2, 3, 4, 5, 6],
            'manual_dial_only' => false,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045553001',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);

        $this->expectException(CallbackOutsideWindowException::class);

        app(DispositionService::class)->apply(
            $lead,
            $user,
            Disposition::Callback,
            callbackAt: Carbon::parse('2026-08-10 22:00:00', 'America/New_York'),
        );
    }
}
