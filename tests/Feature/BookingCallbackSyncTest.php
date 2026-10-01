<?php

namespace Tests\Feature;

use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Pages\ImportAgentCallbacks;
use App\Livewire\Agent\Workspace;
use App\Models\BookingCallbackSchedule;
use App\Models\BookingCallbackSyncRun;
use App\Models\CallingList;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\LeadHistory;
use App\Models\ListAssignment;
use App\Models\User;
use App\Services\Leads\AgentCallbacksProvisioner;
use App\Services\Leads\BookingCallbackSyncService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\Support\CreatesCadences;
use Tests\TestCase;

class BookingCallbackSyncTest extends TestCase
{
    use CreatesCadences, RefreshDatabase;

    private const BOOKING_ID = 'a1EVr00000CbTestAA';

    private const EMPLOYEE_ID = 'a0RVr000009zhVtMAI';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->configureSalesforce();
        Carbon::setTestNow(Carbon::parse('2026-09-23 15:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CompanyContext::clear();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_open_callback_is_assigned_to_the_matching_agent(): void
    {
        [$company, $agent] = $this->companyWithAgent();
        $standard = $this->createCallingList($company->id, overrides: [
            'name' => 'Standard',
            'booking_url_template' => 'https://book.example/tour',
            'booking_param_map' => ['phone' => 'Phone'],
        ]);

        $this->fakeBookings([
            $this->booking([
                'Booking_Notes__c' => 'Navy',
                'First_Name__c' => 'Ada',
                'Last_Name__c' => 'Lovelace',
                'Email__c' => 'ada@example.com',
                'Street__c' => '1 Main St',
                'State__c' => 'FL',
                'Postal_Code__c' => '33101',
                'Lead__c' => '00QVr00000LeadAAAAA',
            ]),
        ]);

        $result = app(BookingCallbackSyncService::class)->sync($company->id);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['agent_match_errors']);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $list = CallingList::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', AgentCallbacksProvisioner::LIST_NAME)
            ->first();

        $this->assertNotNull($lead);
        $this->assertNotNull($list);
        $this->assertSame(LeadStatus::Callback, $lead->status);
        $this->assertSame($agent->id, $lead->callback_owner_id);
        $this->assertSame($list->id, $lead->calling_list_id);
        $this->assertSame('standard', $lead->lead_type);
        $this->assertSame('standard', $list->lead_type);
        $this->assertSame($standard->cadence_id, $list->cadence_id);
        $this->assertSame('https://book.example/tour', $list->booking_url_template);
        $this->assertSame(['phone' => 'Phone'], $list->booking_param_map);
        $this->assertSame('B-43314', $lead->booking_id);
        $this->assertSame('B-43314', $lead->booking_number);
        $this->assertSame(self::BOOKING_ID, $lead->salesforce_booking_id);
        $this->assertSame('Navy', $lead->notes);
        $this->assertSame('Ada', $lead->first_name);
        $this->assertSame('00QVr00000LeadAAAAA', $lead->external_lead_id);
        $this->assertSame('2026-09-24 20:30:00', $lead->callback_at->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue(ListAssignment::withoutGlobalScopes()
            ->where('user_id', $agent->id)
            ->where('calling_list_id', $list->id)
            ->exists());

        $history = LeadHistory::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_type', LeadHistoryType::BookingCallbackSync)
            ->first();

        $this->assertSame('Navy', $history->payload['note']);
        $this->assertSame(self::BOOKING_ID, $history->payload['salesforce_booking_id']);
        $this->assertSame(self::EMPLOYEE_ID, $history->payload['employee_id']);

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            return str_contains($url, "Type__c = 'Callback'")
                && str_contains($url, 'Call_Back_Date__c')
                && str_contains($url, 'Tour_Location_Name__c')
                && str_contains($url, 'CreatedDate')
                && str_contains($url, 'Deposit_Type__c')
                && str_contains($url, '2026-06-25')
                && str_contains($url, '2026-10-23');
        });

        Queue::fake();
        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);
        $this->actingAs($agent, 'agent');

        Livewire::test(Workspace::class)
            ->assertSee('Notes')
            ->assertSee('Navy')
            ->assertSee('Booking')
            ->assertSee('Booking Number')
            ->assertSee('B-43314')
            ->assertSee('Booking Callback');
    }

    public function test_callback_report_fields_are_mapped_to_lead_columns(): void
    {
        [$company] = $this->companyWithAgent();

        $this->fakeBookings([$this->booking([
            'CreatedDate' => '2026-09-20T16:44:35.000+0000',
            'Tour_Location_Name__c' => 'Club Wyndham Palm Aire',
            'Premium_Combined_2__c' => 'RCI 8/7 Vacation Certificate',
            'Deposit_Amount__c' => 40,
            'Deposit_Type__c' => 'Card',
        ])]);

        app(BookingCallbackSyncService::class)->sync($company->id);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();

        $this->assertSame('B-43314', $lead->booking_number);
        $this->assertSame('Club Wyndham Palm Aire', $lead->tour_location);
        $this->assertSame('RCI 8/7 Vacation Certificate', $lead->premiums);
        $this->assertSame('40.00', $lead->deposit_amount);
        $this->assertSame('Card', $lead->deposit_type);
        $this->assertSame('2026-09-20 16:44:35', $lead->created_at->utc()->format('Y-m-d H:i:s'));

        $this->fakeBookings([$this->booking([
            'CreatedDate' => '2026-09-21T10:00:00.000+0000',
            'Tour_Location_Name__c' => 'Updated location',
            'Booking_Notes__c' => 'Updated note',
        ])]);

        $result = app(BookingCallbackSyncService::class)->sync($company->id);

        $lead->refresh();

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame('Club Wyndham Palm Aire', $lead->tour_location);
        $this->assertSame('Navy', $lead->notes);
        $this->assertSame('2026-09-20 16:44:35', $lead->created_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_afternoon_callback_times_fix_salesforce_am_storage(): void
    {
        [$company] = $this->companyWithAgent();

        $this->fakeBookings([$this->booking([
            'Call_Back_Time__c' => '02:00:00.000Z',
            'Callback_Time_Text__c' => '02:00 AM',
        ])]);

        app(BookingCallbackSyncService::class)->sync($company->id);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();

        $this->assertSame('2026-09-24 18:00:00', $lead->callback_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_morning_callback_times_before_noon_stay_am(): void
    {
        [$company] = $this->companyWithAgent();

        $this->fakeBookings([$this->booking([
            'Call_Back_Time__c' => '10:00:00.000Z',
            'Callback_Time_Text__c' => '10:00 AM',
        ])]);

        app(BookingCallbackSyncService::class)->sync($company->id);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();

        $this->assertSame('2026-09-24 14:00:00', $lead->callback_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_blank_notes_stay_null_and_later_sync_does_not_touch_the_lead(): void
    {
        [$company] = $this->companyWithAgent();

        $this->fakeBookings([$this->booking(['Booking_Notes__c' => '   '])]);
        app(BookingCallbackSyncService::class)->sync($company->id);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $this->assertNull($lead->notes);
        $lead->update(['attempt_count' => 4, 'notes' => 'Kept locally']);

        LeadClaim::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'user_id' => $lead->callback_owner_id,
            'claimed_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        $this->fakeBookings([$this->booking([
            'Booking_Notes__c' => 'Wyn getting married',
            'Call_Back_Time__c' => '18:00:00.000Z',
        ])]);
        $result = app(BookingCallbackSyncService::class)->sync($company->id);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $lead->refresh();
        $this->assertSame('Kept locally', $lead->notes);
        $this->assertSame(4, $lead->attempt_count);
        $this->assertSame('2026-09-24 20:30:00', $lead->callback_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($lead->callback_owner_id, LeadClaim::withoutGlobalScopes()->where('lead_id', $lead->id)->value('user_id'));
    }

    public function test_unmatched_agents_still_land_and_record_errors(): void
    {
        $company = Company::factory()->create();
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'salesforce_id' => self::EMPLOYEE_ID,
        ]);
        $inactive = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => false,
            'salesforce_id' => 'a0R000000000001AAA',
        ]);

        $this->fakeBookings([
            $this->booking([
                'Id' => 'a1E000000000001AAA',
                'Name' => 'B-1',
                'Phone_Cleaned__c' => '4045551001',
                'Representative__c' => null,
                'Representative__r' => null,
            ]),
            $this->booking([
                'Id' => 'a1E000000000002AAA',
                'Name' => 'B-2',
                'Phone_Cleaned__c' => '4045551002',
                'Representative__c' => 'a0R000000000002AAA',
                'Representative__r' => ['Name' => 'Missing Rep'],
            ]),
            $this->booking([
                'Id' => 'a1E000000000003AAA',
                'Name' => 'B-3',
                'Phone_Cleaned__c' => '4045551003',
                'Representative__c' => $inactive->salesforce_id,
                'Representative__r' => ['Name' => 'Inactive Rep'],
            ]),
            $this->booking([
                'Id' => 'a1E000000000004AAA',
                'Name' => 'B-4',
                'Phone_Cleaned__c' => '4045551004',
                'Call_Back_Date__c' => null,
            ]),
        ]);

        $result = app(BookingCallbackSyncService::class)->sync($company->id, trigger: 'import_now');

        $this->assertSame(4, $result['created']);
        $this->assertSame(3, $result['agent_match_errors']);
        $this->assertSame(4, Lead::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $blank = Lead::withoutGlobalScopes()->where('phone', '4045551001')->first();
        $missing = Lead::withoutGlobalScopes()->where('phone', '4045551002')->first();
        $inactiveLead = Lead::withoutGlobalScopes()->where('phone', '4045551003')->first();
        $dateless = Lead::withoutGlobalScopes()->where('phone', '4045551004')->first();

        $this->assertNull($blank->callback_owner_id);
        $this->assertNull($missing->callback_owner_id);
        $this->assertNull($inactiveLead->callback_owner_id);
        $this->assertSame($agent->id, $dateless->callback_owner_id);
        $this->assertSame('2026-09-23 15:00:00', $dateless->callback_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(0, ListAssignment::withoutGlobalScopes()->where('user_id', $inactive->id)->count());

        $reasons = collect($result['errors'])->pluck('reason_label')->all();
        $this->assertContains('No representative on booking', $reasons);
        $this->assertContains('No user with this Salesforce Id', $reasons);
        $this->assertContains('User inactive', $reasons);
        $this->assertContains('No callback date', $reasons);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);
        $list = CallingList::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', AgentCallbacksProvisioner::LIST_NAME)
            ->first();

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ImportAgentCallbacks::class)
            ->assertSee('No representative on booking')
            ->assertSee('No user with this Salesforce Id')
            ->assertSee('User inactive')
            ->assertSee('No callback date')
            ->assertSee('B-2')
            ->fillForm(['calling_list_id' => $list->id])
            ->call('import')
            ->assertNotified();
    }

    public function test_import_uses_the_selected_calling_list(): void
    {
        [$company] = $this->companyWithAgent();
        $customList = $this->createCallingList($company->id, overrides: ['name' => 'Field Callbacks']);

        $this->fakeBookings([$this->booking()]);
        app(BookingCallbackSyncService::class)->sync($company->id, callingListId: $customList->id);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $run = BookingCallbackSyncRun::latestForCompany($company->id);

        $this->assertSame($customList->id, $lead->calling_list_id);
        $this->assertSame($customList->id, $run->calling_list_id);
    }

    public function test_already_imported_booking_is_not_updated_on_later_sync(): void
    {
        $company = Company::factory()->create();
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'salesforce_id' => null,
        ]);

        $this->fakeBookings([$this->booking()]);
        app(BookingCallbackSyncService::class)->sync($company->id);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $this->assertNull($lead->callback_owner_id);
        $this->assertSame(1, BookingCallbackSyncRun::latestForCompany($company->id)->error_count);

        $agent->update(['salesforce_id' => self::EMPLOYEE_ID]);
        $this->fakeBookings([$this->booking(['Booking_Notes__c' => 'Changed in Salesforce'])]);
        $result = app(BookingCallbackSyncService::class)->sync($company->id);

        $lead->refresh();
        $this->assertNull($lead->callback_owner_id);
        $this->assertSame('Navy', $lead->notes);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['agent_match_errors']);
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_existing_callable_phone_is_attached_and_dnc_is_skipped(): void
    {
        [$company, $agent] = $this->companyWithAgent();
        $callable = Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551212',
            'first_name' => 'Old',
            'status' => LeadStatus::Callable,
            'lead_type' => 'standard',
            'attempt_count' => 2,
            'imported_at' => now(),
        ]);
        Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'phone' => '4045551313',
            'status' => LeadStatus::Dnc,
            'lead_type' => 'standard',
            'imported_at' => now(),
        ]);

        $this->fakeBookings([
            $this->booking(),
            $this->booking([
                'Id' => 'a1E000000000005AAA',
                'Name' => 'B-900',
                'Phone_Cleaned__c' => '4045551313',
            ]),
        ]);

        $result = app(BookingCallbackSyncService::class)->sync($company->id);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['skipped_dnc_terminal']);
        $this->assertSame(2, Lead::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $callable->refresh();
        $this->assertSame(LeadStatus::Callback, $callable->status);
        $this->assertSame(self::BOOKING_ID, $callable->salesforce_booking_id);
        $this->assertSame($agent->id, $callable->callback_owner_id);
        $this->assertSame(2, $callable->attempt_count);

        $dnc = Lead::withoutGlobalScopes()->where('phone', '4045551313')->first();
        $this->assertSame(LeadStatus::Dnc, $dnc->status);
        $this->assertNull($dnc->salesforce_booking_id);
    }

    public function test_cancelled_booking_closes_the_local_callback(): void
    {
        [$company] = $this->companyWithAgent();

        $this->fakeBookings([$this->booking()]);
        app(BookingCallbackSyncService::class)->sync($company->id);

        $this->fakeBookings([$this->booking(['Status__c' => 'Cancelled'])]);
        $result = app(BookingCallbackSyncService::class)->sync($company->id);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $this->assertSame(1, $result['closed']);
        $this->assertSame(LeadStatus::Terminal, $lead->status);
        $this->assertNull($lead->callback_owner_id);
        $this->assertSame(self::BOOKING_ID, $lead->salesforce_booking_id);
    }

    public function test_not_interested_callback_is_not_reopened_on_later_sync(): void
    {
        [$company] = $this->companyWithAgent();

        $this->fakeBookings([$this->booking()]);
        app(BookingCallbackSyncService::class)->sync($company->id);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $lead->update([
            'status' => LeadStatus::Terminal,
            'callback_owner_id' => null,
            'callback_at' => null,
            'attempt_count' => 1,
        ]);

        $this->fakeBookings([$this->booking(['Booking_Notes__c' => 'Still open in Salesforce'])]);
        $result = app(BookingCallbackSyncService::class)->sync($company->id);

        $lead->refresh();
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped_dnc_terminal']);
        $this->assertSame(LeadStatus::Terminal, $lead->status);
        $this->assertNull($lead->callback_owner_id);
        $this->assertNull($lead->callback_at);
        $this->assertSame(1, $lead->attempt_count);
        $this->assertSame('Navy', $lead->notes);
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_agent_rescheduled_callback_date_is_not_overwritten(): void
    {
        [$company] = $this->companyWithAgent();

        $this->fakeBookings([$this->booking()]);
        app(BookingCallbackSyncService::class)->sync($company->id);

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $lead->update([
            'callback_at' => Carbon::parse('2026-12-01 22:32:00', 'UTC'),
            'attempt_count' => 1,
        ]);

        $this->fakeBookings([$this->booking(['Booking_Notes__c' => 'Checking schedule'])]);
        $result = app(BookingCallbackSyncService::class)->sync($company->id);

        $lead->refresh();
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped_dnc_terminal']);
        $this->assertSame(LeadStatus::Callback, $lead->status);
        $this->assertSame('2026-12-01 22:32:00', $lead->callback_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Navy', $lead->notes);
        $this->assertSame(1, $lead->attempt_count);
    }

    public function test_date_outside_the_window_is_not_pulled_and_a_missing_phone_is_skipped(): void
    {
        [$company] = $this->companyWithAgent();

        $this->fakeBookings([
            $this->booking([
                'Id' => 'a1E000000000006AAA',
                'Name' => 'B-future',
                'Phone_Cleaned__c' => '4045551414',
                'Call_Back_Date__c' => '2027-01-01',
            ]),
            $this->booking([
                'Id' => 'a1E000000000007AAA',
                'Name' => 'B-nophone',
                'Phone_Cleaned__c' => null,
                'Phone__c' => '123',
                'Phone_2__c' => null,
            ]),
            $this->booking([
                'Id' => 'a1E000000000008AAA',
                'Name' => 'B-overdue',
                'Phone_Cleaned__c' => '4045551515',
                'Call_Back_Date__c' => '2026-08-01',
            ]),
        ]);

        $result = app(BookingCallbackSyncService::class)->sync($company->id);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped_no_phone']);
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertSame('B-overdue', Lead::withoutGlobalScopes()->where('company_id', $company->id)->value('booking_id'));
    }

    public function test_dry_run_and_an_overlapping_run_write_nothing(): void
    {
        [$company] = $this->companyWithAgent();
        $this->fakeBookings([$this->booking()]);

        $dry = app(BookingCallbackSyncService::class)->sync($company->id, dryRun: true);

        $this->assertSame(1, $dry['created']);
        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
        $this->assertSame(0, BookingCallbackSyncRun::withoutGlobalScopes()->count());
        $this->assertSame(0, LeadHistory::withoutGlobalScopes()->count());
        $this->assertNull(CallingList::withoutGlobalScopes()->where('name', AgentCallbacksProvisioner::LIST_NAME)->first());

        $this->artisan('salesforce:sync-booking-callbacks', [
            '--dry-run' => true,
            '--company' => (string) $company->id,
        ])->assertSuccessful()->expectsOutputToContain('Dry run');

        $this->assertSame(0, Lead::withoutGlobalScopes()->count());

        $lock = Cache::lock(BookingCallbackSyncService::lockKey($company->id), 120);
        $this->assertTrue($lock->get());

        $refused = app(BookingCallbackSyncService::class)->sync($company->id);

        $this->assertTrue($refused['refused']);
        $this->assertSame(0, Lead::withoutGlobalScopes()->count());

        $this->artisan('salesforce:sync-booking-callbacks', [
            '--company' => (string) $company->id,
        ])->assertFailed()->expectsOutputToContain('already running');

        $lock->release();
    }

    public function test_import_now_and_a_matching_schedule_slot_call_the_same_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-23 11:00:00', 'UTC'));

        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);
        $this->createCallingList($company->id, overrides: ['name' => 'Standard']);
        $list = app(AgentCallbacksProvisioner::class)->ensure($company->id);

        $mock = Mockery::mock(BookingCallbackSyncService::class);
        $mock->shouldReceive('sync')->once()->with($company->id, false, 'schedule', $list->id)->andReturn($this->blankResult());
        $mock->shouldReceive('sync')->once()->with($company->id, false, 'import_now', $list->id)->andReturn($this->blankResult());
        $this->app->instance(BookingCallbackSyncService::class, $mock);

        $this->artisan('salesforce:sync-booking-callbacks', ['--scheduled' => true])->assertSuccessful();

        $this->assertSame(
            '2026-09-23 07:00',
            BookingCallbackSchedule::withoutGlobalScopes()->where('company_id', $company->id)->value('last_run_slot'),
        );

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ImportAgentCallbacks::class)
            ->fillForm(['calling_list_id' => $list->id])
            ->call('import')
            ->assertNotified();
    }

    public function test_schedule_runs_only_on_a_matching_slot(): void
    {
        [$company] = $this->companyWithAgent();
        app(AgentCallbacksProvisioner::class)->ensure($company->id);
        $this->fakeBookings([$this->booking()]);

        Carbon::setTestNow(Carbon::parse('2026-09-23 16:00:00', 'UTC'));
        $this->artisan('salesforce:sync-booking-callbacks', ['--scheduled' => true])->assertSuccessful();
        $this->assertSame(0, Lead::withoutGlobalScopes()->count());

        BookingCallbackSchedule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->update(['enabled' => false]);

        Carbon::setTestNow(Carbon::parse('2026-09-23 11:00:00', 'UTC'));
        $this->artisan('salesforce:sync-booking-callbacks', ['--scheduled' => true])->assertSuccessful();
        $this->assertSame(0, Lead::withoutGlobalScopes()->count());

        BookingCallbackSchedule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->update(['enabled' => true]);

        $this->artisan('salesforce:sync-booking-callbacks', ['--scheduled' => true])->assertSuccessful();
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertSame(
            '2026-09-23 07:00',
            BookingCallbackSchedule::withoutGlobalScopes()->where('company_id', $company->id)->value('last_run_slot'),
        );

        $this->artisan('salesforce:sync-booking-callbacks', ['--scheduled' => true])->assertSuccessful();
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_import_now_notification_is_danger_when_agents_do_not_match(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'active' => true,
        ]);
        $this->createCallingList($company->id, overrides: ['name' => 'Standard']);
        $list = app(AgentCallbacksProvisioner::class)->ensure($company->id);

        $this->fakeBookings([
            $this->booking([
                'Representative__c' => null,
                'Representative__r' => null,
            ]),
        ]);

        CompanyContext::set($company->id);

        Livewire::actingAs($admin)
            ->test(ImportAgentCallbacks::class)
            ->fillForm(['calling_list_id' => $list->id])
            ->call('import')
            ->assertNotified('1 agent match errors');

        $lead = Lead::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $this->assertNotNull($lead);
        $this->assertNull($lead->callback_owner_id);
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyWithAgent(): array
    {
        $company = Company::factory()->create();
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Agent,
            'active' => true,
            'salesforce_id' => self::EMPLOYEE_ID,
        ]);

        return [$company, $agent];
    }

    private function configureSalesforce(): void
    {
        config([
            'services.qualification.client_id' => 'client',
            'services.qualification.client_secret' => 'secret',
            'services.qualification.instance_url' => 'https://example.salesforce.com',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function fakeBookings(array $records): void
    {
        $this->app->forgetInstance(HttpFactory::class);
        Facade::clearResolvedInstance(HttpFactory::class);

        Http::fake([
            '*oauth2/token*' => Http::response(['access_token' => 'token']),
            '*query*' => Http::response([
                'records' => $records,
                'done' => true,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function booking(array $overrides = []): array
    {
        return array_merge([
            'Id' => self::BOOKING_ID,
            'Name' => 'B-43314',
            'Type__c' => 'Callback',
            'Status__c' => 'New',
            'First_Name__c' => 'Pat',
            'Last_Name__c' => 'Lee',
            'First_Name_2__c' => null,
            'Last_Name_2__c' => null,
            'Phone__c' => '(404) 555-1212',
            'Phone_Cleaned__c' => '4045551212',
            'Phone_2__c' => null,
            'Email__c' => 'pat@example.com',
            'Email_2__c' => null,
            'Booking_Notes__c' => 'Navy',
            'Street__c' => null,
            'Unit_Number__c' => null,
            'State__c' => 'GA',
            'Postal_Code__c' => '30301',
            'Age_Range__c' => null,
            'Income__c' => null,
            'Gender__c' => null,
            'Marital__c' => null,
            'HomeOwner_or_Renter__c' => null,
            'Lead__c' => null,
            'Representative__c' => self::EMPLOYEE_ID,
            'Representative__r' => ['Name' => 'Iranays Ferro'],
            'Call_Back_Date__c' => '2026-09-24',
            'Call_Back_Time__c' => '16:30:00.000Z',
            'CreatedDate' => '2026-09-20T16:44:35.000+0000',
            'Tour_Location_Name__c' => 'Club Wyndham Palm Aire',
            'Deposit_Type__c' => 'No Deposit',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function blankResult(): array
    {
        return [
            'refused' => false,
            'failed' => false,
            'message' => '',
            'created' => 0,
            'updated' => 0,
            'skipped_no_phone' => 0,
            'skipped_dnc_terminal' => 0,
            'closed' => 0,
            'agent_match_errors' => 0,
            'error_count' => 0,
            'errors' => [],
        ];
    }
}
