<?php

namespace App\Services\Salesforce;

use Carbon\Carbon;

class SalesforceBookingCallbackClient
{
    public function __construct(
        private readonly SalesforceClient $salesforce,
    ) {}

    public function isConfigured(): bool
    {
        return $this->salesforce->isConfigured();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetch(Carbon $today): array
    {
        $window = $this->window($today);

        return array_map(
            fn (array $record): array => $this->parse($record),
            $this->salesforce->query($this->soql($window['from'], $window['to'])),
        );
    }

    /**
     * @return array{from: string, to: string}
     */
    public function window(Carbon $today): array
    {
        $today = $today->copy()->startOfDay();

        return [
            'from' => $today->copy()->subDays($this->lookbackDays())->toDateString(),
            'to' => $today->copy()->addDays($this->horizonDays())->toDateString(),
        ];
    }

    public function soql(string $from, string $to): string
    {
        $fields = config('services.salesforce.booking_callbacks.fields');
        $object = (string) config('services.salesforce.booking_callbacks.object', 'Booking__c');
        $typeField = $fields['type'];
        $typeValue = str_replace("'", "\\'", (string) config('services.salesforce.booking_callbacks.type', 'Callback'));
        $dateField = $fields['callback_date'];

        $selectFields = [
            $fields['id'],
            $fields['name'],
            $fields['type'],
            $fields['status'],
            $fields['first_name'],
            $fields['last_name'],
            $fields['first_name_2'],
            $fields['last_name_2'],
            $fields['phone'],
            $fields['phone_cleaned'],
            $fields['phone_2'],
            $fields['email'],
            $fields['email_2'],
            $fields['notes'],
            $fields['street'],
            $fields['unit'],
            $fields['state'],
            $fields['postal'],
            $fields['age_range'],
            $fields['income'],
            $fields['gender'],
            $fields['marital'],
            $fields['home_owner'],
            $fields['lead'],
            $fields['representative'],
            'Representative__r.Name',
            $fields['callback_date'],
            $fields['callback_time'],
            $fields['callback_time_text'],
            $fields['created_at'],
            $fields['tour_location'],
            $fields['deposit_type'],
        ];

        foreach (config('services.salesforce.booking_callbacks.optional_soql_fields', []) as $key) {
            if (is_string($key) && isset($fields[$key])) {
                $selectFields[] = $fields[$key];
            }
        }

        $select = implode(', ', $selectFields);

        return 'SELECT '.$select
            .' FROM '.$object
            ." WHERE {$typeField} = '{$typeValue}'"
            ." AND ({$dateField} = null OR ({$dateField} >= {$from} AND {$dateField} <= {$to}))";
    }

    public function inWindow(?string $date, Carbon $today): bool
    {
        if ($date === null || trim($date) === '') {
            return true;
        }

        try {
            $day = Carbon::parse($date, $today->timezone)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        $window = $this->window($today);

        return $day->toDateString() >= $window['from'] && $day->toDateString() <= $window['to'];
    }

    /**
     * @param  list<string>  $statuses
     */
    public function isOpen(?string $status): bool
    {
        return in_array($status, $this->openStatuses(), true);
    }

    /**
     * @return list<string>
     */
    public function openStatuses(): array
    {
        $statuses = config('services.salesforce.booking_callbacks.open_statuses', []);

        return array_values(array_filter($statuses, 'is_string'));
    }

    public function horizonDays(): int
    {
        return (int) config('services.salesforce.booking_callbacks.horizon_days', 30);
    }

    public function lookbackDays(): int
    {
        return (int) config('services.salesforce.booking_callbacks.overdue_lookback_days', 90);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function parse(array $record): array
    {
        $fields = config('services.salesforce.booking_callbacks.fields');
        $relationship = $record['Representative__r'] ?? null;
        $relationshipName = is_array($relationship) ? ($relationship['Name'] ?? null) : null;

        return [
            'id' => $this->stringValue($record[$fields['id']] ?? null),
            'name' => $this->stringValue($record[$fields['name']] ?? null),
            'type' => $this->stringValue($record[$fields['type']] ?? null),
            'status' => $this->stringValue($record[$fields['status']] ?? null),
            'first_name' => $this->stringValue($record[$fields['first_name']] ?? null),
            'last_name' => $this->stringValue($record[$fields['last_name']] ?? null),
            'first_name_2' => $this->stringValue($record[$fields['first_name_2']] ?? null),
            'last_name_2' => $this->stringValue($record[$fields['last_name_2']] ?? null),
            'phone' => $this->stringValue($record[$fields['phone']] ?? null),
            'phone_cleaned' => $this->stringValue($record[$fields['phone_cleaned']] ?? null),
            'phone_2' => $this->stringValue($record[$fields['phone_2']] ?? null),
            'email' => $this->stringValue($record[$fields['email']] ?? null),
            'email_2' => $this->stringValue($record[$fields['email_2']] ?? null),
            'notes' => $this->stringValue($record[$fields['notes']] ?? null),
            'street' => $this->stringValue($record[$fields['street']] ?? null),
            'unit' => $this->stringValue($record[$fields['unit']] ?? null),
            'state' => $this->stringValue($record[$fields['state']] ?? null),
            'postal' => $this->stringValue($record[$fields['postal']] ?? null),
            'age_range' => $this->stringValue($record[$fields['age_range']] ?? null),
            'income' => $this->stringValue($record[$fields['income']] ?? null),
            'gender' => $this->stringValue($record[$fields['gender']] ?? null),
            'marital' => $this->stringValue($record[$fields['marital']] ?? null),
            'home_owner' => $this->stringValue($record[$fields['home_owner']] ?? null),
            'lead_id' => $this->stringValue($record[$fields['lead']] ?? null),
            'representative_id' => $this->stringValue($record[$fields['representative']] ?? null),
            'representative_name' => $this->stringValue($relationshipName),
            'callback_date' => $this->stringValue($record[$fields['callback_date']] ?? null),
            'callback_time' => $this->stringValue($record[$fields['callback_time']] ?? null),
            'callback_time_text' => $this->stringValue($record[$fields['callback_time_text']] ?? null),
            'booking_created_at' => $this->stringValue($record[$fields['created_at']] ?? null),
            'tour_location' => $this->stringValue($record[$fields['tour_location']] ?? null),
            'premiums' => $this->stringValue($record[$fields['premiums']] ?? null),
            'deposit_amount' => $this->decimalValue($record[$fields['deposit_amount']] ?? null),
            'deposit_type' => $this->stringValue($record[$fields['deposit_type']] ?? null),
        ];
    }

    private function decimalValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (string) $value;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
