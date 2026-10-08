<?php

namespace App\Services\Leads;

use App\Enums\BookingCallbackSyncErrorReason;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\BookingCallbackSyncError;
use App\Models\BookingCallbackSyncRun;
use App\Models\CallingList;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\ListAssignment;
use App\Models\User;
use App\Services\Salesforce\SalesforceBookingCallbackClient;
use App\Support\CompanyTimezone;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BookingCallbackSyncService
{
    public function __construct(
        private readonly SalesforceBookingCallbackClient $client,
        private readonly AgentCallbacksProvisioner $provisioner,
    ) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public static function lockKey(int $companyId): string
    {
        return 'booking-callback-sync:'.$companyId;
    }

    /**
     * @return array{
     *     refused: bool,
     *     failed: bool,
     *     message: string,
     *     created: int,
     *     updated: int,
     *     skipped_no_phone: int,
     *     skipped_dnc_terminal: int,
     *     closed: int,
     *     agent_match_errors: int,
     *     error_count: int,
     *     errors: list<array{
     *         booking_number: ?string,
     *         salesforce_booking_id: ?string,
     *         representative_name: ?string,
     *         employee_id: ?string,
     *         reason: string,
     *         reason_label: string
     *     }>
     * }
     */
    public function sync(
        int $companyId,
        bool $dryRun = false,
        string $trigger = 'command',
        ?int $callingListId = null,
    ): array {
        $stats = $this->blankStats();

        if (! $this->client->isConfigured()) {
            $stats['failed'] = true;
            $stats['message'] = 'Salesforce credentials are not configured.';

            return $stats;
        }

        $lock = Cache::lock(self::lockKey($companyId), 600);

        if (! $lock->get()) {
            $stats['refused'] = true;
            $stats['message'] = 'A booking callback import is already running.';

            return $stats;
        }

        try {
            $timezone = CompanyTimezone::for($companyId);
            $today = Carbon::now($timezone)->startOfDay();
            $records = $this->client->fetch($today);
            $list = $this->resolveList($companyId, $callingListId, $dryRun);
            $users = User::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereNotNull('salesforce_id')
                ->where('salesforce_id', '!=', '')
                ->get();

            foreach ($records as $record) {
                $this->applyRecord($companyId, $record, $today, $users, $list, $dryRun, $stats);
            }

            if (! $dryRun) {
                $this->persistRun($companyId, $trigger, $stats, $list?->id);
            }

            return $stats;
        } finally {
            $lock->release();
        }
    }

    /**
     * Backfill venue and event from Salesforce for callback leads that already exist in OPC.
     *
     * @return array{failed: bool, message: string, scanned: int, updated: int, unchanged: int, not_found: int}
     */
    public function backfillSourceFields(?int $companyId = null, bool $dryRun = false): array
    {
        $result = [
            'failed' => false,
            'message' => '',
            'scanned' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'not_found' => 0,
        ];

        if (! $this->client->isConfigured()) {
            $result['failed'] = true;
            $result['message'] = 'Salesforce credentials are not configured.';

            return $result;
        }

        $query = Lead::withoutGlobalScopes()
            ->where('status', LeadStatus::Callback)
            ->whereNotNull('salesforce_booking_id')
            ->where('salesforce_booking_id', '!=', '');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $leads = $query->get(['id', 'company_id', 'salesforce_booking_id', 'venue', 'event', 'tour_location']);
        $result['scanned'] = $leads->count();

        if ($leads->isEmpty()) {
            return $result;
        }

        $records = collect($this->client->fetchByIds($leads->pluck('salesforce_booking_id')->all()))
            ->keyBy(fn (array $record): string => (string) ($record['id'] ?? ''));

        foreach ($leads as $lead) {
            $record = $records->get((string) $lead->salesforce_booking_id);

            if ($record === null) {
                $result['not_found']++;

                continue;
            }

            if ($this->refreshSourceFields($lead, $record, $dryRun)) {
                $result['updated']++;
            } else {
                $result['unchanged']++;
            }
        }

        return $result;
    }

    public function resolveList(int $companyId, ?int $callingListId, bool $dryRun): ?CallingList
    {
        if ($callingListId !== null) {
            return CallingList::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereKey($callingListId)
                ->firstOrFail();
        }

        if ($dryRun) {
            return null;
        }

        return $this->provisioner->ensure($companyId);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $stats
     */
    private function applyRecord(
        int $companyId,
        array $record,
        Carbon $today,
        Collection $users,
        ?CallingList $list,
        bool $dryRun,
        array &$stats,
    ): void {
        $bookingId = $record['id'] ?? null;

        if (! is_string($bookingId) || $bookingId === '') {
            return;
        }

        $type = (string) config('services.salesforce.booking_callbacks.type', 'Callback');

        if (($record['type'] ?? null) !== null && $record['type'] !== $type) {
            return;
        }

        if (! $this->client->inWindow($record['callback_date'] ?? null, $today)) {
            return;
        }

        $existing = $this->leadByBookingId($companyId, $bookingId);

        if (! $this->client->isOpen($record['status'] ?? null)) {
            $this->closeIfCallback($existing, $record, $dryRun, $stats);

            return;
        }

        if ($existing) {
            $this->refreshSourceFields($existing, $record, $dryRun, $stats);

            return;
        }

        [$phone, $phone2] = $this->phones($record);

        if ($phone === null) {
            $stats['skipped_no_phone']++;

            return;
        }

        $match = $this->matchUser($users, $record['representative_id'] ?? null);
        $callbackAt = $this->callbackAt($record, $today);
        $errors = [];

        if ($match['error'] instanceof BookingCallbackSyncErrorReason) {
            $errors[] = $match['error'];
        }

        if (($record['callback_date'] ?? null) === null) {
            $errors[] = BookingCallbackSyncErrorReason::NoCallbackDate;
        }

        $byPhone = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('phone', $phone)
            ->first();

        if ($byPhone) {
            if ($this->isProtectedFromCallbackReopen($byPhone) || $this->alreadyImportedCallback($byPhone)) {
                $stats['skipped_dnc_terminal']++;

                return;
            }

            $this->updateLead($byPhone, $record, $phone, $phone2, $callbackAt, $match['user'], $list, $errors, $dryRun, $stats);

            return;
        }

        $stats['created']++;
        $this->rememberErrors($stats, $record, $errors);

        if ($dryRun || $list === null) {
            return;
        }

        $lead = Lead::withoutGlobalScopes()->create($this->attributes(
            companyId: $companyId,
            record: $record,
            phone: $phone,
            phone2: $phone2,
            callbackAt: $callbackAt,
            owner: $match['user'],
            list: $list,
            existing: null,
        ));

        $this->applyBookingCreatedAt($lead, $record);

        $this->assignOwner($match['user'], $list);
        $this->writeHistory($lead, $record, $callbackAt, 'created');
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<BookingCallbackSyncErrorReason>  $errors
     * @param  array<string, mixed>  $stats
     */
    private function updateLead(
        Lead $lead,
        array $record,
        string $phone,
        ?string $phone2,
        Carbon $callbackAt,
        ?User $owner,
        ?CallingList $list,
        array $errors,
        bool $dryRun,
        array &$stats,
    ): void {
        $stats['updated']++;
        $this->rememberErrors($stats, $record, $errors);

        if ($dryRun || $list === null) {
            return;
        }

        $lead->update($this->attributes(
            companyId: (int) $lead->company_id,
            record: $record,
            phone: $phone,
            phone2: $phone2,
            callbackAt: $callbackAt,
            owner: $owner,
            list: $list,
            existing: $lead,
        ));

        $this->assignOwner($owner, $list);
        $this->writeHistory($lead, $record, $callbackAt, 'updated');
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $stats
     */
    private function closeIfCallback(?Lead $lead, array $record, bool $dryRun, array &$stats): void
    {
        if (! $lead || $lead->status !== LeadStatus::Callback) {
            return;
        }

        $stats['closed']++;

        if ($dryRun) {
            return;
        }

        $notes = $this->notes($record['notes'] ?? null);

        $lead->forceFill([
            'status' => LeadStatus::Terminal,
            'callback_owner_id' => null,
        ]);

        if ($notes !== null) {
            $lead->notes = $notes;
        }

        $lead->save();

        $this->writeHistory($lead, $record, $lead->callback_at, 'closed');
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function attributes(
        int $companyId,
        array $record,
        string $phone,
        ?string $phone2,
        Carbon $callbackAt,
        ?User $owner,
        CallingList $list,
        ?Lead $existing,
    ): array {
        $notes = $this->notes($record['notes'] ?? null);
        $phoneToStore = $phone;

        if ($existing && $existing->phone !== $phone && $this->phoneIsTaken($companyId, $phone, $existing->id)) {
            $phoneToStore = $existing->phone;
        }

        $attributes = [
            'company_id' => $companyId,
            'phone' => $phoneToStore,
            'phone_2' => $phone2,
            'first_name' => $record['first_name'] ?? null,
            'last_name' => $record['last_name'] ?? null,
            'first_name_2' => $record['first_name_2'] ?? null,
            'last_name_2' => $record['last_name_2'] ?? null,
            'email' => $record['email'] ?? $record['email_2'] ?? null,
            'address' => $record['street'] ?? null,
            'address_2' => $record['unit'] ?? null,
            'state' => $this->stateCode($record['state'] ?? null),
            'zip' => $this->postal($record['postal'] ?? null),
            'age_range' => $record['age_range'] ?? null,
            'annual_income' => $record['income'] ?? null,
            'gender' => $record['gender'] ?? null,
            'marital_status' => $record['marital'] ?? null,
            'home_owner' => $record['home_owner'] ?? null,
            'booking_number' => $record['name'] ?? null,
            'booking_id' => $record['name'] ?? null,
            'salesforce_booking_id' => $record['id'],
            'venue' => $this->nullable($record['venue'] ?? null),
            'event' => $this->nullable($record['event'] ?? null),
            'tour_location' => $this->nullable($record['tour_location'] ?? null),
            'premiums' => $this->nullable($record['premiums'] ?? null),
            'deposit_amount' => $this->nullable($record['deposit_amount'] ?? null),
            'deposit_type' => $this->nullable($record['deposit_type'] ?? null),
            'callback_at' => $callbackAt,
            'callback_owner_id' => $owner?->id,
            'calling_list_id' => $list->id,
            'lead_type' => 'standard',
            'status' => LeadStatus::Callback,
        ];

        if ($notes !== null || $existing === null) {
            $attributes['notes'] = $notes;
        }

        if ($existing === null) {
            $attributes['imported_at'] = now();
            $attributes['attempt_count'] = 0;
        }

        $externalId = $record['lead_id'] ?? null;

        if (is_string($externalId) && $externalId !== '' && ($existing === null || $existing->external_lead_id === null || $existing->external_lead_id === '')) {
            if (! $this->externalIdTaken($companyId, $externalId, $existing?->id)) {
                $attributes['external_lead_id'] = $externalId;
            }
        }

        return $attributes;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{user: ?User, error: ?BookingCallbackSyncErrorReason}
     */
    private function matchUser(Collection $users, ?string $employeeId): array
    {
        $employeeId = $this->nullable($employeeId);

        if ($employeeId === null) {
            return ['user' => null, 'error' => BookingCallbackSyncErrorReason::NoRepresentative];
        }

        $matches = $users->filter(
            fn (User $user): bool => $this->salesforceIdsMatch((string) $user->salesforce_id, $employeeId),
        );

        $active = $matches->first(fn (User $user): bool => (bool) $user->active);

        if ($active instanceof User) {
            return ['user' => $active, 'error' => null];
        }

        if ($matches->isNotEmpty()) {
            return ['user' => null, 'error' => BookingCallbackSyncErrorReason::UserInactive];
        }

        return ['user' => null, 'error' => BookingCallbackSyncErrorReason::NoUser];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function callbackAt(array $record, Carbon $today): Carbon
    {
        $date = $record['callback_date'] ?? null;

        if (! is_string($date) || trim($date) === '') {
            return now();
        }

        $clock = $this->callbackClock($record);

        return Carbon::parse($date.' '.$clock, $today->timezone)->utc();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function callbackClock(array $record): string
    {
        $text = $record['callback_time_text'] ?? null;

        if (is_string($text) && preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', trim($text), $matches) === 1) {
            return $this->twelveHourClock((int) $matches[1], (int) $matches[2], strtoupper($matches[3]));
        }

        $time = $record['callback_time'] ?? null;

        if (! is_string($time) || preg_match('/^(\d{2}):(\d{2}):(\d{2})/', $time, $matches) !== 1) {
            return '00:00:00';
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour >= 1 && $hour <= 6) {
            return $this->twelveHourClock($hour, $minute, 'PM');
        }

        if ($hour === 0) {
            return sprintf('00:%02d:00', $minute);
        }

        if ($hour === 12) {
            return sprintf('12:%02d:00', $minute);
        }

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function applyBookingCreatedAt(Lead $lead, array $record): void
    {
        $bookingCreatedAt = $this->bookingCreatedAt($record);

        if (! $bookingCreatedAt instanceof Carbon) {
            return;
        }

        $lead->forceFill(['created_at' => $bookingCreatedAt])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function bookingCreatedAt(array $record): ?Carbon
    {
        $value = $record['booking_created_at'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function twelveHourClock(int $hour, int $minute, string $meridian): string
    {
        if ($meridian === 'AM' && $hour >= 1 && $hour <= 6) {
            $meridian = 'PM';
        }

        if ($meridian === 'PM' && $hour !== 12) {
            $hour += 12;
        }

        if ($meridian === 'AM' && $hour === 12) {
            $hour = 0;
        }

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0: ?string, 1: ?string}
     */
    private function phones(array $record): array
    {
        $primary = PhoneNormalizer::normalize($record['phone_cleaned'] ?? null)
            ?? PhoneNormalizer::normalize($record['phone'] ?? null)
            ?? PhoneNormalizer::normalize($record['phone_2'] ?? null);
        $secondary = PhoneNormalizer::normalize($record['phone_2'] ?? null);

        if ($secondary === $primary) {
            $secondary = null;
        }

        return [$primary, $secondary];
    }

    private function isProtectedFromCallbackReopen(Lead $lead): bool
    {
        return in_array($lead->status, [LeadStatus::Dnc, LeadStatus::Booked, LeadStatus::Terminal], true);
    }

    private function alreadyImportedCallback(Lead $lead): bool
    {
        $bookingId = $lead->salesforce_booking_id;

        return is_string($bookingId) && $bookingId !== '';
    }

    private function leadByBookingId(int $companyId, string $bookingId): ?Lead
    {
        $query = Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($leads) use ($bookingId): void {
                $leads->where('salesforce_booking_id', $bookingId);

                if (strlen($bookingId) >= 15) {
                    $prefix = substr($bookingId, 0, 15);
                    $leads->orWhereRaw('substr(salesforce_booking_id, 1, 15) = ?', [$prefix]);
                }
            });

        return $query->first();
    }

    private function salesforceIdsMatch(string $left, string $right): bool
    {
        if ($left === $right) {
            return true;
        }

        if (strlen($left) < 15 || strlen($right) < 15) {
            return false;
        }

        return substr($left, 0, 15) === substr($right, 0, 15);
    }

    private function assignOwner(?User $owner, CallingList $list): void
    {
        if (! $owner) {
            return;
        }

        ListAssignment::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $list->company_id,
            'user_id' => $owner->id,
            'calling_list_id' => $list->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function writeHistory(Lead $lead, array $record, mixed $callbackAt, string $action): void
    {
        $notes = $this->notes($record['notes'] ?? null);
        $callback = $callbackAt instanceof Carbon ? $callbackAt->toIso8601String() : null;

        LeadHistory::withoutGlobalScopes()->create([
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'actor_id' => null,
            'event_type' => LeadHistoryType::BookingCallbackSync,
            'occurred_at' => now(),
            'payload' => [
                'salesforce_booking_id' => $record['id'] ?? null,
                'booking_number' => $record['name'] ?? null,
                'employee_id' => $record['representative_id'] ?? null,
                'representative_name' => $record['representative_name'] ?? null,
                'callback_at' => $callback,
                'action' => $action,
                'note' => $notes ?? ($action === 'closed' ? 'Closed in Salesforce' : null),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $record
     * @param  list<BookingCallbackSyncErrorReason>  $errors
     */
    private function rememberErrors(array &$stats, array $record, array $errors): void
    {
        foreach ($errors as $error) {
            $stats['error_count']++;

            if ($error->isAgentMatch()) {
                $stats['agent_match_errors']++;
            }

            $stats['errors'][] = [
                'booking_number' => $record['name'] ?? null,
                'salesforce_booking_id' => $record['id'] ?? null,
                'representative_name' => $record['representative_name'] ?? null,
                'employee_id' => $record['representative_id'] ?? null,
                'reason' => $error->value,
                'reason_label' => $error->label(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function persistRun(int $companyId, string $trigger, array $stats, ?int $callingListId): void
    {
        $started = now();

        $run = BookingCallbackSyncRun::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'calling_list_id' => $callingListId,
            'trigger' => $trigger,
            'started_at' => $started,
            'finished_at' => $started,
            'created_count' => $stats['created'],
            'updated_count' => $stats['updated'],
            'skipped_no_phone_count' => $stats['skipped_no_phone'],
            'skipped_dnc_terminal_count' => $stats['skipped_dnc_terminal'],
            'closed_count' => $stats['closed'],
            'error_count' => $stats['error_count'],
        ]);

        foreach ($stats['errors'] as $error) {
            BookingCallbackSyncError::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'booking_callback_sync_run_id' => $run->id,
                'booking_number' => $error['booking_number'],
                'salesforce_booking_id' => $error['salesforce_booking_id'],
                'representative_name' => $error['representative_name'],
                'employee_id' => $error['employee_id'],
                'reason' => $error['reason'],
            ]);
        }
    }

    private function phoneIsTaken(int $companyId, string $phone, int $exceptLeadId): bool
    {
        return Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('phone', $phone)
            ->where('id', '!=', $exceptLeadId)
            ->exists();
    }

    private function externalIdTaken(int $companyId, string $externalId, ?int $exceptLeadId): bool
    {
        return Lead::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('external_lead_id', $externalId)
            ->when($exceptLeadId, fn ($query) => $query->where('id', '!=', $exceptLeadId))
            ->exists();
    }

    private function notes(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $notes = trim($value);

        return $notes === '' ? null : $notes;
    }

    private function nullable(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>|null  $stats
     */
    private function refreshSourceFields(Lead $lead, array $record, bool $dryRun, ?array &$stats = null): bool
    {
        $values = [
            'venue' => $this->nullable($record['venue'] ?? null),
            'event' => $this->nullable($record['event'] ?? null),
        ];

        $dirty = false;

        foreach ($values as $key => $value) {
            if ($lead->{$key} !== $value) {
                $dirty = true;

                break;
            }
        }

        if (! $dirty) {
            return false;
        }

        if ($stats !== null) {
            $stats['source_refreshed'] = ($stats['source_refreshed'] ?? 0) + 1;
        }

        if ($dryRun) {
            return true;
        }

        $lead->update($values);

        return true;
    }

    private function stateCode(?string $state): ?string
    {
        if ($state === null) {
            return null;
        }

        $state = strtoupper(trim($state));

        return strlen($state) === 2 ? $state : null;
    }

    private function postal(?string $postal): ?string
    {
        if ($postal === null) {
            return null;
        }

        $postal = trim($postal);

        if ($postal === '') {
            return null;
        }

        return substr($postal, 0, 10);
    }

    /**
     * @return array{
     *     refused: bool,
     *     failed: bool,
     *     message: string,
     *     created: int,
     *     updated: int,
     *     skipped_no_phone: int,
     *     skipped_dnc_terminal: int,
     *     closed: int,
     *     agent_match_errors: int,
     *     error_count: int,
     *     errors: list<array<string, mixed>>
     * }
     */
    private function blankStats(): array
    {
        return [
            'refused' => false,
            'failed' => false,
            'message' => '',
            'created' => 0,
            'updated' => 0,
            'source_refreshed' => 0,
            'skipped_no_phone' => 0,
            'skipped_dnc_terminal' => 0,
            'closed' => 0,
            'agent_match_errors' => 0,
            'error_count' => 0,
            'errors' => [],
        ];
    }
}
