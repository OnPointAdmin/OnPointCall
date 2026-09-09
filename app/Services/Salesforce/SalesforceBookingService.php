<?php

namespace App\Services\Salesforce;

use App\DataTransferObjects\BookingCheckResult;
use App\Enums\BookingCheckStatus;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\QualifyBatch;
use App\Services\Qualify\QualifyBatchCounters;
use App\Support\CompanyTimezone;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class SalesforceBookingService
{
    public function __construct(
        private readonly SalesforceBookingClient $client,
    ) {}

    /**
     * @param  Collection<int, Lead>  $leads
     */
    public function checkLeads(Collection $leads, ?int $actorId = null, ?int $qualifyBatchId = null): void
    {
        if ($leads->isEmpty()) {
            return;
        }

        foreach ($leads as $lead) {
            $this->markPending($lead);
        }

        if (! $this->client->isConfigured()) {
            foreach ($leads as $lead) {
                $this->persistResult(
                    $lead,
                    new BookingCheckResult(
                        status: BookingCheckStatus::Error,
                        error: 'Salesforce credentials are not configured.',
                    ),
                    $actorId,
                    $qualifyBatchId,
                );
            }

            return;
        }

        $policyByLeadId = $this->bookingPolicyByLeadId($leads, $qualifyBatchId);
        $companyId = (int) $leads->first()->company_id;
        $today = Carbon::now(CompanyTimezone::for($companyId))->startOfDay();

        $phonesCleaned = [];
        $phone2Variants = [];
        $emails = [];
        $leadContactById = [];

        foreach ($leads as $lead) {
            $contact = $this->leadContact($lead);
            $leadContactById[$lead->id] = $contact;

            if ($contact['phone'] !== null) {
                $phonesCleaned[] = $contact['phone'];
                $phone2Variants = [...$phone2Variants, ...$this->client->phone2QueryVariants($contact['phone'])];
            }

            if ($contact['phone_2'] !== null) {
                $phonesCleaned[] = $contact['phone_2'];
                $phone2Variants = [...$phone2Variants, ...$this->client->phone2QueryVariants($contact['phone_2'])];
            }

            if ($contact['email'] !== null) {
                $emails[] = $contact['email'];
            }
        }

        try {
            $records = $this->client->findBookings($phonesCleaned, $phone2Variants, $emails);
        } catch (Throwable $exception) {
            foreach ($leads as $lead) {
                $this->persistResult(
                    $lead,
                    new BookingCheckResult(
                        status: BookingCheckStatus::Error,
                        error: $exception->getMessage(),
                    ),
                    $actorId,
                    $qualifyBatchId,
                );
            }

            return;
        }

        foreach ($leads as $lead) {
            $policy = $policyByLeadId[$lead->id] ?? ['exclude_future' => true, 'exclude_past' => true];
            $contact = $leadContactById[$lead->id];
            $result = $this->resolveLeadResult($records, $contact, $policy, $today);
            $this->persistResult($lead, $result, $actorId, $qualifyBatchId);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array{phone: ?string, phone_2: ?string, email: ?string}  $contact
     * @param  array{exclude_future: bool, exclude_past: bool}  $policy
     */
    private function resolveLeadResult(
        array $records,
        array $contact,
        array $policy,
        Carbon $today,
    ): BookingCheckResult {
        $matches = [];
        $hasFuture = false;
        $hasPast = false;

        foreach ($records as $record) {
            $matchedFields = $this->client->matchedFieldsForLead(
                $record,
                $contact['phone'],
                $contact['phone_2'],
                $contact['email'],
            );

            if ($matchedFields === []) {
                continue;
            }

            $parsed = $this->client->parseRecord($record);
            $isFuture = $this->client->isFutureBooking($parsed['tour_date'], $parsed['status'], $today);
            $isPast = $this->client->isPastBooking($parsed['tour_date'], $today);

            if (! $isFuture && ! $isPast) {
                continue;
            }

            $matches[] = [
                ...$parsed,
                'matched_fields' => $matchedFields,
                'classification' => $isFuture ? 'future' : 'past',
            ];

            if ($isFuture) {
                $hasFuture = true;
            }

            if ($isPast) {
                $hasPast = true;
            }
        }

        if ($hasFuture && $policy['exclude_future']) {
            return new BookingCheckResult(
                status: BookingCheckStatus::FutureHit,
                matches: $matches,
            );
        }

        if ($hasPast && $policy['exclude_past']) {
            return new BookingCheckResult(
                status: BookingCheckStatus::PastHit,
                matches: $matches,
            );
        }

        return new BookingCheckResult(
            status: BookingCheckStatus::Clear,
            matches: $matches,
        );
    }

    /**
     * @return array{phone: ?string, phone_2: ?string, email: ?string}
     */
    private function leadContact(Lead $lead): array
    {
        $phone = PhoneNormalizer::normalize($lead->phone);
        $phone2 = PhoneNormalizer::normalize($lead->phone_2);

        if ($phone2 === $phone) {
            $phone2 = null;
        }

        $email = is_string($lead->email) ? strtolower(trim($lead->email)) : null;
        $email = $email !== '' ? $email : null;

        return [
            'phone' => $phone,
            'phone_2' => $phone2,
            'email' => $email,
        ];
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array<int, array{exclude_future: bool, exclude_past: bool}>
     */
    private function bookingPolicyByLeadId(Collection $leads, ?int $qualifyBatchId): array
    {
        if ($qualifyBatchId !== null) {
            $batch = QualifyBatch::withoutGlobalScopes()->find($qualifyBatchId);

            if ($batch) {
                $policy = [
                    'exclude_future' => (bool) $batch->exclude_future_bookings,
                    'exclude_past' => (bool) $batch->exclude_past_bookings,
                ];

                return $leads->mapWithKeys(fn (Lead $lead): array => [$lead->id => $policy])->all();
            }
        }

        $batchIds = $leads->pluck('import_batch_id')->filter()->unique()->all();
        $policyByBatchId = [];

        if ($batchIds !== []) {
            $policyByBatchId = ImportBatch::withoutGlobalScopes()
                ->whereIn('id', $batchIds)
                ->get()
                ->mapWithKeys(fn (ImportBatch $batch): array => [
                    $batch->id => [
                        'exclude_future' => (bool) $batch->exclude_future_bookings,
                        'exclude_past' => (bool) $batch->exclude_past_bookings,
                    ],
                ])
                ->all();
        }

        $policyByLeadId = [];

        foreach ($leads as $lead) {
            $batchId = $lead->import_batch_id;
            $policyByLeadId[$lead->id] = $batchId !== null
                ? ($policyByBatchId[$batchId] ?? ['exclude_future' => true, 'exclude_past' => true])
                : ['exclude_future' => true, 'exclude_past' => true];
        }

        return $policyByLeadId;
    }

    private function markPending(Lead $lead): void
    {
        $previousStatus = $lead->booking_check_status;
        $batchId = $lead->import_batch_id;

        DB::transaction(function () use ($lead, $previousStatus, $batchId): void {
            if ($batchId) {
                $this->moveCompletedCounterToPending($batchId, $previousStatus);
            }

            $lead->update([
                'booking_check_status' => BookingCheckStatus::Pending,
                'booking_check_last_error' => null,
            ]);
        });
    }

    private function persistResult(
        Lead $lead,
        BookingCheckResult $result,
        ?int $actorId,
        ?int $qualifyBatchId = null,
    ): void {
        DB::transaction(function () use ($lead, $result, $actorId, $qualifyBatchId): void {
            $previousLeadStatus = $lead->status;

            $updates = [
                'booking_check_status' => $result->status,
                'booking_checked_at' => now(),
                'booking_check_last_error' => $result->error,
                'booking_check_result' => $result->error ? $lead->booking_check_result : $result->toArray(),
            ];

            $newLeadStatus = $this->leadStatusFor($result->status, $previousLeadStatus);

            if ($newLeadStatus !== null) {
                $updates['status'] = $newLeadStatus;
            }

            $lead->update($updates);

            LeadHistory::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $actorId,
                'event_type' => LeadHistoryType::BookingCheck,
                'occurred_at' => now(),
                'payload' => [
                    'status' => $result->status->value,
                    'matches' => $result->matches,
                    'error' => $result->error,
                ],
            ]);

            if ($newLeadStatus !== null && $previousLeadStatus !== $newLeadStatus) {
                LeadHistory::withoutGlobalScopes()->create([
                    'company_id' => $lead->company_id,
                    'lead_id' => $lead->id,
                    'actor_id' => $actorId,
                    'event_type' => LeadHistoryType::StatusChange,
                    'occurred_at' => now(),
                    'payload' => [
                        'from' => $previousLeadStatus->value,
                        'to' => $newLeadStatus->value,
                        'reason' => 'booking_'.$result->status->value,
                    ],
                ]);
            }

            if ($lead->import_batch_id) {
                $this->completeBatchCounter($lead->import_batch_id, $result->status);
            }

            app(QualifyBatchCounters::class)->completeBooking($qualifyBatchId, $result->status);
        });
    }

    private function leadStatusFor(BookingCheckStatus $status, LeadStatus $current): ?LeadStatus
    {
        if (! in_array($status, [BookingCheckStatus::FutureHit, BookingCheckStatus::PastHit], true)) {
            return null;
        }

        if (in_array($current, [LeadStatus::Dnc, LeadStatus::Booked], true)) {
            return null;
        }

        return LeadStatus::Booked;
    }

    private function moveCompletedCounterToPending(int $batchId, ?BookingCheckStatus $previous): void
    {
        if ($previous === null || $previous === BookingCheckStatus::Pending) {
            return;
        }

        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'booking_check_pending' => $batch->booking_check_pending + 1,
        ];

        match ($previous) {
            BookingCheckStatus::Clear => $updates['booking_check_clear'] = max(0, $batch->booking_check_clear - 1),
            BookingCheckStatus::FutureHit => $updates['booking_future_hit'] = max(0, $batch->booking_future_hit - 1),
            BookingCheckStatus::PastHit => $updates['booking_past_hit'] = max(0, $batch->booking_past_hit - 1),
            BookingCheckStatus::Error => $updates['booking_check_error'] = max(0, $batch->booking_check_error - 1),
            BookingCheckStatus::Pending => null,
        };

        $batch->update($updates);
    }

    private function completeBatchCounter(int $batchId, BookingCheckStatus $status): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->lockForUpdate()->find($batchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'booking_check_pending' => max(0, $batch->booking_check_pending - 1),
        ];

        match ($status) {
            BookingCheckStatus::Clear => $updates['booking_check_clear'] = $batch->booking_check_clear + 1,
            BookingCheckStatus::FutureHit => $updates['booking_future_hit'] = $batch->booking_future_hit + 1,
            BookingCheckStatus::PastHit => $updates['booking_past_hit'] = $batch->booking_past_hit + 1,
            BookingCheckStatus::Error => $updates['booking_check_error'] = $batch->booking_check_error + 1,
            BookingCheckStatus::Pending => null,
        };

        $batch->update($updates);
    }
}
