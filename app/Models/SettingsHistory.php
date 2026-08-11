<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingsHistory extends Model
{
    use BelongsToCompany;

    protected $table = 'settings_history';

    protected $fillable = [
        'company_id',
        'user_id',
        'setting_key',
        'before_value',
        'after_value',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'before_value' => 'array',
            'after_value' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
