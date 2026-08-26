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

        if ($params === []) {
            $formParam = array_key_first($paramMap) ?: 'id';
            $params[$formParam] = (string) ($lead->external_lead_id ?: $lead->id);
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
