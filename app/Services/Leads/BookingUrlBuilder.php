<?php

namespace App\Services\Leads;

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

        $paramMap = $lead->callingList?->booking_param_map
            ?? $settings?->booking_param_map
            ?? [];

        if ($paramMap === []) {
            $paramMap = ['id' => 'external_lead_id'];
        }

        $params = $this->buildParams($lead, $paramMap);

        if ($params === [] && $lead->external_lead_id) {
            $params['id'] = $lead->external_lead_id;
        }

        if ($params === []) {
            return $template;
        }

        $separator = str_contains($template, '?') ? '&' : '?';

        return $template.$separator.http_build_query($params);
    }

    /**
     * @param  array<string, string>  $paramMap
     * @return array<string, string>
     */
    private function buildParams(Lead $lead, array $paramMap): array
    {
        $params = [];

        foreach ($paramMap as $formParam => $leadField) {
            $value = $this->resolveLeadField($lead, $leadField);

            if ($value !== null && $value !== '') {
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
}
