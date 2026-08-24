<?php

namespace Tests\Feature;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Exceptions\CallbackOutsideWindowException;
use App\Exceptions\MissingDispositionReasonException;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\DispositionReason;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\LeadHistory;
use App\Models\StateRule;
use App\Models\User;
use App\Services\Leads\DispositionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class DispositionServiceTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    public function test_skip_moves_lead_to_bottom_without_incrementing_attempts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $cadence = $this->createCadenceWithDayParts($company->id);
        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'cadence_id' => $cadence->id,
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

        $reason = DispositionReason::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'disposition' => Disposition::Skip,
            'label' => 'Busy signal',
            'sort_order' => 1,
            'active' => true,
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
            reason: (string) $reason->id,
        );

        $this->assertSame(2, $updated->attempt_count);
        $this->assertSame(6, $updated->queue_rank);
        $this->assertEquals(now(), $updated->last_attempt_at);
        $this->assertSame('evening', $updated->next_day_part);
        $this->assertSame($user->id, $updated->last_skipped_by_user_id);
        $this->assertDatabaseMissing('lead_claims', ['lead_id' => $lead->id]);

        $history = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::Skip)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('Busy signal', $history->payload['reason'] ?? null);
        $this->assertSame(LeadStatus::Callable, $updated->status);
    }

    public function test_non_skip_disposition_clears_last_skipped_by(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $cadence = $this->createCadenceWithDayParts($company->id);
        $list = CallingList::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'lead_type' => 'standard',
            'cadence_id' => $cadence->id,
            'active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552004',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'calling_list_id' => $list->id,
            'last_skipped_by_user_id' => $user->id,
            'imported_at' => now(),
        ]);

        $updated = app(DispositionService::class)->apply(
            $lead,
            $user,
            Disposition::NoAnswer,
        );

        $this->assertNull($updated->last_skipped_by_user_id);
    }

    public function test_skip_requires_configured_reason(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045552003',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $this->expectException(MissingDispositionReasonException::class);

        app(DispositionService::class)->apply(
            $lead,
            $user,
            Disposition::Skip,
        );
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

    public function test_disposition_persists_optional_note_on_history_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 14:00:00', 'America/New_York'));

        $company = Company::factory()->create();
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045554001',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'attempt_count' => 0,
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

        app(DispositionService::class)->apply(
            $lead,
            $user,
            Disposition::Booked,
            note: '  Asked to call after lunch  ',
        );

        $this->assertDatabaseHas('lead_history', [
            'lead_id' => $lead->id,
            'actor_id' => $user->id,
        ]);

        $history = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::Disposition)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertIsArray($history->payload);
        $this->assertSame(Disposition::Booked->value, $history->payload['disposition'] ?? null);
        $this->assertSame('Asked to call after lunch', $history->payload['note'] ?? null);
    }

    public function test_not_interested_requires_configured_reason(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045554100',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $this->expectException(MissingDispositionReasonException::class);

        app(DispositionService::class)->apply(
            $lead,
            $user,
            Disposition::NotInterested,
        );
    }

    public function test_not_qualified_stores_reason_on_history(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
        ]);
        $reason = DispositionReason::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'disposition' => Disposition::NotQualified,
            'label' => 'Income too low',
            'sort_order' => 1,
            'active' => true,
        ]);
        $lead = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045554101',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        app(DispositionService::class)->apply(
            $lead,
            $user,
            Disposition::NotQualified,
            reason: (string) $reason->id,
        );

        $history = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::Disposition)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('Income too low', $history->payload['reason'] ?? null);
        $this->assertSame(LeadStatus::Terminal, $lead->fresh()->status);
    }
}
