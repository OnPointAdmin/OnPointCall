<?php

namespace App\Models;

use App\Casts\AssociativeJsonMap;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'booking_url_template',
        'booking_param_map',
        'max_attempts',
        'claim_ttl_minutes',
        'dashboard_email_enabled',
        'dashboard_email_send_time',
        'dashboard_email_timezone',
        'soft_score_originator',
        'soft_score_base_url',
    ];

    protected function casts(): array
    {
        return [
            'booking_param_map' => AssociativeJsonMap::class,
            'dashboard_email_enabled' => 'boolean',
        ];
    }
}
