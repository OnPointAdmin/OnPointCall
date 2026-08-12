<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Model;

class ImportMapping extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'name',
        'column_map',
        'lead_type',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
