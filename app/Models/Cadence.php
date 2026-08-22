<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cadence extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'name',
        'prioritize_unattempted',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'prioritize_unattempted' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function dayParts(): HasMany
    {
        return $this->hasMany(CadenceDayPart::class)->orderBy('rotation_order');
    }

    public function attemptGaps(): HasMany
    {
        return $this->hasMany(CadenceAttemptGap::class)->orderBy('after_attempt');
    }

    public function callingLists(): HasMany
    {
        return $this->hasMany(CallingList::class);
    }
}
