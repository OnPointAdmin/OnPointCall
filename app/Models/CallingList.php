<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallingList extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'name',
        'lead_type',
        'cadence',
        'max_attempts_override',
        'active',
        'booking_url_template',
        'booking_param_map',
    ];

    protected function casts(): array
    {
        return [
            'cadence' => 'array',
            'booking_param_map' => 'array',
            'active' => 'boolean',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function availableLeads(): HasMany
    {
        return $this->leads()->where('status', LeadStatus::Callable);
    }

    public function listAssignments(): HasMany
    {
        return $this->hasMany(ListAssignment::class);
    }
}
