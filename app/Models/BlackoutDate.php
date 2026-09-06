<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use App\Support\NpaToState;
use App\Support\UsStates;
use Illuminate\Database\Eloquent\Model;

class BlackoutDate extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'date',
        'state_code',
        'label',
    ];

    protected $attributes = [
        'state_code' => UsStates::ALL,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function appliesTo(Lead $lead): bool
    {
        $stateCode = strtoupper(trim((string) $this->state_code));

        if ($stateCode === '' || $stateCode === UsStates::ALL) {
            return true;
        }

        $leadState = strtoupper(trim((string) $lead->state));

        if ($leadState !== '' && $leadState === $stateCode) {
            return true;
        }

        return NpaToState::phoneMatchesState($stateCode, $lead->phone)
            || NpaToState::phoneMatchesState($stateCode, $lead->phone_2);
    }
}
