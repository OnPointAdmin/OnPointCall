<?php

namespace App\Services\Leads;

use App\Casts\AssociativeJsonMap;
use App\Models\AppSetting;
use App\Models\Lead;

class BookingUrlBuilder
{
    public function build(Lead $lead): ?string
    {
        $lead->loadMissing('callingList');

        $settings = AppSetting::withoutGlobalScopes()
            ->where('company_id', $lead->company_id)
            ->first();

        $template = $lead->callingList?->booking_url_template
            ?? $settings?->booking_url_template;

        if (! $template) {
            return null;
        }

        $listMap = AssociativeJsonMap::normalize($lead->callingList?->booking_param_map);
        $settingsMap = AssociativeJsonMap::normalize($settings?->booking_param_map);
        $paramMap = $listMap !== [] ? $listMap : $settingsMap;

        if ($paramMap === []) {
            $paramMap = ['id' => 'external_lead_id'];
        }

        $params = $this->buildParams($lead, $paramMap);

        $salesforceLeadId = $this->salesforceLeadId($lead);

        if ($salesforceLeadId !== null) {
            $params['2ff7-7114-0d49'] = $salesforceLeadId;
        }

        if ($params === []) {
            $formParam = array_key_first($paramMap) ?: 'id';
            $fallback = (string) ($lead->external_lead_id ?: $lead->id);

            if ($this->shouldIncludeValue($fallback) && $formParam !== '2ff7-7114-0d49') {
                $params[$formParam] = $fallback;
            }
        }

        if ($params === []) {
            return $template;
        }

        $separator = str_contains($template, '?') ? '&' : '?';

        return $template.$separator.$this->queryString($params);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function queryString(array $params): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            $pairs[] = rawurlencode((string) $key).'='.$this->encodeQueryValue((string) $value);
        }

        return implode('&', $pairs);
    }

    /**
     * Encode only characters that would break the query string. FormYoula
     * prefills from the raw query, so %40 is shown instead of @.
     */
    private function encodeQueryValue(string $value): string
    {
        return strtr($value, [
            '%' => '%25',
            '&' => '%26',
            '=' => '%3D',
            '#' => '%23',
            '+' => '%2B',
            ' ' => '%20',
        ]);
    }

    /**
     * @param  array<string, string>  $paramMap
     * @return array<string, string>
     */
    private function buildParams(Lead $lead, array $paramMap): array
    {
        $params = [];

        foreach ($paramMap as $formParam => $leadField) {
            if ($formParam === '2ff7-7114-0d49') {
                continue;
            }

            $value = $this->resolveLeadField($lead, $leadField);

            if ($this->shouldIncludeValue($value)) {
                $params[$formParam] = (string) $value;
            }
        }

        return $params;
    }

    private function resolveLeadField(Lead $lead, string $field): mixed
    {
        if ($lead->isFillable($field) || array_key_exists($field, $lead->getAttributes())) {
            return $lead->getAttribute($field);
        }

        $extra = $lead->extra_fields ?? [];

        return $extra[$field] ?? null;
    }

    private function shouldIncludeValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    private function salesforceLeadId(Lead $lead): ?string
    {
        $extra = is_array($lead->extra_fields) ? $lead->extra_fields : [];

        $candidates = [
            $lead->external_lead_id,
            $extra['LeadId'] ?? null,
            $extra['lead_id'] ?? null,
            $extra['Lead ID'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && preg_match('/^00Q[a-zA-Z0-9]{12}([a-zA-Z0-9]{3})?$/', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }
}
