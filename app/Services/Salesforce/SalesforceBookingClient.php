<?php

namespace App\Services\Salesforce;

use App\Support\PhoneNormalizer;

class SalesforceBookingClient
{
    public function __construct(
        private readonly SalesforceClient $salesforce,
    ) {}

    public function isConfigured(): bool
    {
        return $this->salesforce->isConfigured();
    }

    /**
     * @param  list<string>  $phonesCleaned
     * @param  list<string>  $phone2Variants
     * @param  list<string>  $emails
     * @return list<array<string, mixed>>
     */
    public function findBookings(array $phonesCleaned, array $phone2Variants, array $emails): array
    {
        $phonesCleaned = array_values(array_unique(array_filter($phonesCleaned)));
        $phone2Variants = array_values(array_unique(array_filter($phone2Variants)));
        $emails = array_values(array_unique(array_filter($emails)));

        if ($phonesCleaned === [] && $phone2Variants === [] && $emails === []) {
            return [];
        }

        $fields = config('services.salesforce.bookings.fields');
        $object = (string) config('services.salesforce.bookings.object', 'Booking__c');

        $select = implode(', ', [
            $fields['id'],
            $fields['phone'],
            $fields['phone_cleaned'],
            $fields['phone_2'],
            $fields['email'],
            $fields['email_2'],
            $fields['tour_date'],
            $fields['status'],
        ]);

        $conditions = [];

        if ($phonesCleaned !== []) {
            $conditions[] = $fields['phone_cleaned'].' IN ('.$this->quotedList($phonesCleaned).')';
        }

        if ($phone2Variants !== []) {
            $conditions[] = $fields['phone_2'].' IN ('.$this->quotedList($phone2Variants).')';
        }

        if ($emails !== []) {
            $conditions[] = $fields['email'].' IN ('.$this->quotedList($emails).')';
            $conditions[] = $fields['email_2'].' IN ('.$this->quotedList($emails).')';
        }

        $soql = 'SELECT '.$select.' FROM '.$object.' WHERE '.implode(' OR ', $conditions);

        return $this->salesforce->query($soql);
    }

    /**
     * @return list<string>
     */
    public function phone2QueryVariants(string $normalizedPhone): array
    {
        if (strlen($normalizedPhone) !== 10) {
            return [];
        }

        return array_values(array_unique([
            $normalizedPhone,
            sprintf('(%s) %s-%s', substr($normalizedPhone, 0, 3), substr($normalizedPhone, 3, 3), substr($normalizedPhone, 6)),
            sprintf('%s-%s-%s', substr($normalizedPhone, 0, 3), substr($normalizedPhone, 3, 3), substr($normalizedPhone, 6)),
            '1'.$normalizedPhone,
            '+1'.$normalizedPhone,
            '+1 '.$normalizedPhone,
        ]));
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<array{field: string, value: string}>
     */
    public function matchedFieldsForLead(array $record, ?string $phone, ?string $phone2, ?string $email): array
    {
        $fields = config('services.salesforce.bookings.fields');
        $matches = [];

        $phoneCleaned = isset($record[$fields['phone_cleaned']])
            ? PhoneNormalizer::normalize((string) $record[$fields['phone_cleaned']])
            : null;
        $phone2Value = isset($record[$fields['phone_2']])
            ? PhoneNormalizer::normalize((string) $record[$fields['phone_2']])
            : null;
        $emailValue = $this->normalizeEmail($record[$fields['email']] ?? null);
        $email2Value = $this->normalizeEmail($record[$fields['email_2']] ?? null);
        $leadEmail = $this->normalizeEmail($email);

        if ($phone !== null && $phoneCleaned === $phone) {
            $matches[] = ['field' => 'phone', 'value' => $phone];
        }

        if ($phone !== null && $phone2Value === $phone) {
            $matches[] = ['field' => 'phone', 'value' => $phone, 'booking_field' => 'phone_2'];
        }

        if ($phone2 !== null && $phoneCleaned === $phone2) {
            $matches[] = ['field' => 'phone_2', 'value' => $phone2];
        }

        if ($phone2 !== null && $phone2Value === $phone2) {
            $matches[] = ['field' => 'phone_2', 'value' => $phone2, 'booking_field' => 'phone_2'];
        }

        if ($leadEmail !== null && ($leadEmail === $emailValue || $leadEmail === $email2Value)) {
            $matches[] = [
                'field' => 'email',
                'value' => $leadEmail,
                'booking_field' => $leadEmail === $emailValue ? 'email' : 'email_2',
            ];
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function parseRecord(array $record): array
    {
        $fields = config('services.salesforce.bookings.fields');

        return [
            'salesforce_id' => isset($record[$fields['id']]) ? (string) $record[$fields['id']] : null,
            'phone' => isset($record[$fields['phone']]) ? (string) $record[$fields['phone']] : null,
            'phone_cleaned' => isset($record[$fields['phone_cleaned']]) ? (string) $record[$fields['phone_cleaned']] : null,
            'phone_2' => isset($record[$fields['phone_2']]) ? (string) $record[$fields['phone_2']] : null,
            'email' => isset($record[$fields['email']]) ? (string) $record[$fields['email']] : null,
            'email_2' => isset($record[$fields['email_2']]) ? (string) $record[$fields['email_2']] : null,
            'tour_date' => isset($record[$fields['tour_date']]) ? (string) $record[$fields['tour_date']] : null,
            'status' => isset($record[$fields['status']]) ? (string) $record[$fields['status']] : null,
        ];
    }

    public function isFutureBooking(?string $tourDate, ?string $status, \Carbon\Carbon $today): bool
    {
        if ($tourDate === null || trim($tourDate) === '') {
            return false;
        }

        $futureStatuses = config('services.salesforce.bookings.future_statuses', []);

        if (! in_array($status, $futureStatuses, true)) {
            return false;
        }

        try {
            $date = \Carbon\Carbon::parse($tourDate)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $date->greaterThanOrEqualTo($today->copy()->startOfDay());
    }

    public function isPastBooking(?string $tourDate, \Carbon\Carbon $today): bool
    {
        if ($tourDate === null || trim($tourDate) === '') {
            return false;
        }

        try {
            $date = \Carbon\Carbon::parse($tourDate)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $date->lessThan($today->copy()->startOfDay());
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = strtolower(trim($value));

        return $email !== '' ? $email : null;
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedList(array $values): string
    {
        return implode(', ', array_map(
            static fn (string $value): string => "'".str_replace("'", "\\'", $value)."'",
            $values,
        ));
    }
}
