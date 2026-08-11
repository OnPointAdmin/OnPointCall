<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Model;

class BlackoutDate extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'date',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
