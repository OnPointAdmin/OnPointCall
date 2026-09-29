<?php

namespace App\Support;

use App\Models\Lead;
use Illuminate\Support\Collection;

class LeadBookingFields
{
    /**
     * @return list<array{label: string, value: string}>
     */
    public static function rows(Lead $lead): array
    {
        $bookingNumber = filled($lead->booking_number) ? $lead->booking_number : $lead->booking_id;

        $rows = [
            ['label' => 'Booking Number', 'value' => $bookingNumber],
            ['label' => 'Booking Id', 'value' => $lead->salesforce_booking_id],
            ['label' => 'Tour location', 'value' => $lead->tour_location],
            ['label' => 'Tour date start', 'value' => $lead->tour_date_start],
            ['label' => 'Tour date', 'value' => $lead->tour_date],
            ['label' => 'Premiums', 'value' => $lead->premiums],
            ['label' => 'Tour result', 'value' => $lead->tour_result],
            ['label' => 'Tour / no show', 'value' => $lead->tour_or_no_show],
            ['label' => 'Deposit amount', 'value' => self::depositAmountDisplay($lead->deposit_amount)],
            ['label' => 'Deposit type', 'value' => $lead->deposit_type],
        ];

        return collect($rows)
            ->filter(fn (array $row): bool => $row['value'] !== null && $row['value'] !== '')
            ->values()
            ->all();
    }

    public static function hasData(Lead $lead): bool
    {
        if ($lead->lead_type === 'tnb') {
            return true;
        }

        if (filled($lead->salesforce_booking_id)) {
            return true;
        }

        return self::rows($lead) !== [];
    }

    /**
     * @return Collection<int, array{label: string, value: string}>
     */
    public static function extraRowsForTnb(Lead $lead): Collection
    {
        return collect($lead->extra_fields ?? [])
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value, string $key): array => [
                'label' => ucwords(str_replace('_', ' ', $key)),
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ])
            ->values();
    }

    private static function depositAmountDisplay(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return is_numeric($amount)
            ? rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.')
            : (string) $amount;
    }
}
