<?php

namespace App\Support;

use App\Models\Lead;

class LeadDisplayFields
{
    /**
     * @var array<string, string>
     */
    public const AGENT_FIELD_LABELS = [
        'address' => 'Address',
        'address_2' => 'Address 2',
        'zip' => 'Zip',
        'email' => 'Email',
        'age_range' => 'Age range',
        'annual_income' => 'Annual income',
        'marital_status' => 'Marital status',
        'gender' => 'Gender',
        'home_owner' => 'Homeowner',
        'original_lead_submit_date' => 'Original submit date',
        'booking_id' => 'Booking ID',
        'phone_2' => 'Phone 2',
        'first_name_2' => 'First name 2',
        'last_name_2' => 'Last name 2',
        'tour_location' => 'Tour location',
        'tour_date_start' => 'Tour date start',
        'tour_date' => 'Tour date',
        'premiums' => 'Premiums',
        'tour_result' => 'Tour result',
        'tour_or_no_show' => 'Tour / no show',
        'external_lead_id' => 'Lead ID',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'phone' => 'Phone',
        'city' => 'City',
        'state' => 'State',
    ];

    public static function labelFor(string $attribute): string
    {
        return self::AGENT_FIELD_LABELS[$attribute] ?? ucwords(str_replace('_', ' ', $attribute));
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public static function agentFields(Lead $lead): array
    {
        $fields = [];

        foreach (self::AGENT_FIELD_LABELS as $attribute => $label) {
            $value = $lead->{$attribute};

            if ($value === null || $value === '') {
                continue;
            }

            if ($attribute === 'phone_2') {
                $value = PhoneNormalizer::formatForDisplay((string) $value) ?? $value;
            }

            $fields[] = [
                'label' => $label,
                'value' => (string) $value,
            ];
        }

        foreach ($lead->extra_fields ?? [] as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $fields[] = [
                'label' => self::humanizeKey((string) $key),
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ];
        }

        return $fields;
    }

    private static function humanizeKey(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
