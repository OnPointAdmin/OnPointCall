<?php

namespace App\Enums;

enum LeadTablePreset: string
{
    case AllLeads = 'all_leads';
    case CallingList = 'calling_list';
    case Batch = 'batch';
    case Qualify = 'qualify';
    case Assign = 'assign';
    case Callbacks = 'callbacks';

    /**
     * @return list<string>
     */
    public function starterColumnNames(): array
    {
        return match ($this) {
            self::Batch => [
                'id',
                'phone',
                'first_name',
                'last_name',
                'status',
                'soft_score_code',
                'rnd_status',
                'qualification_status',
                'dnc_status',
                'booking_check_status',
                'error',
            ],
            self::Qualify, self::Assign => [
                'phone',
                'first_name',
                'last_name',
                'state',
                'status',
                'last_disposition',
                'attempt_count',
                'imported_at',
            ],
            self::Callbacks => [
                'phone',
                'first_name',
                'callback_at',
                'callbackOwner.name',
                'callingList.name',
                'callback_owner_active',
            ],
            self::CallingList => [
                'phone',
                'external_lead_id',
                'first_name',
                'last_name',
                'state',
                'venue',
                'event',
                'status',
                'last_disposition',
                'last_attempt_at',
                'attempt_count',
                'calling_list_assigned_at',
                'lead_type',
                'soft_score_code',
                'soft_score_status',
                'qualification_status',
                'dnc_status',
            ],
            self::AllLeads => [
                'phone',
                'external_lead_id',
                'first_name',
                'last_name',
                'state',
                'venue',
                'event',
                'status',
                'last_disposition',
                'last_attempt_at',
                'attempt_count',
                'calling_list_assigned_at',
                'callingList.name',
                'lead_type',
                'soft_score_code',
                'soft_score_status',
                'qualification_status',
                'dnc_status',
            ],
        };
    }

    /**
     * @return array{column: string, direction: string}
     */
    public function defaultSort(): array
    {
        return match ($this) {
            self::Batch => ['column' => 'phone', 'direction' => 'asc'],
            self::Qualify, self::Assign => ['column' => 'imported_at', 'direction' => 'desc'],
            self::Callbacks => ['column' => 'callback_at', 'direction' => 'asc'],
            default => ['column' => 'imported_at', 'direction' => 'desc'],
        };
    }

    public function usesPoolSourceScope(): bool
    {
        return in_array($this, [self::Qualify, self::Assign], true);
    }

    public function appliesAssignableScope(): bool
    {
        return $this === self::Assign;
    }

    public function hidesCallingListColumn(): bool
    {
        return $this === self::CallingList;
    }

    public function hidesCallingListFilter(): bool
    {
        return $this === self::CallingList;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultTableFilters(): array
    {
        if (! $this->usesPoolSourceScope()) {
            return [];
        }

        return [
            'lead_type' => ['value' => 'standard'],
            'calling_list_id' => ['value' => 'holding'],
            'qualified_partners_match' => ['value' => QualifiedPartnersMatch::InList->value],
        ];
    }

    public function usesSlideOverView(): bool
    {
        return in_array($this, [self::CallingList, self::Qualify, self::Assign], true);
    }
}
