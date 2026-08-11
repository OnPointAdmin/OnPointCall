<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Model;

class StateRule extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'state_code',
        'window_start',
        'window_end',
        'permitted_weekdays',
        'manual_dial_only',
    ];

    protected function casts(): array
    {
        return [
            'permitted_weekdays' => 'array',
            'manual_dial_only' => 'boolean',
        ];
    }
}
