<?php

namespace App\Models;

use App\Enums\CadenceWaitUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CadenceDayPart extends Model
{
    protected $fillable = [
        'cadence_id',
        'day_part',
        'rotation_order',
        'enabled',
        'window_start',
        'window_end',
        'wait_after_value',
        'wait_after_unit',
    ];

    protected function casts(): array
    {
        return [
            'rotation_order' => 'integer',
            'enabled' => 'boolean',
            'wait_after_value' => 'integer',
            'wait_after_unit' => CadenceWaitUnit::class,
        ];
    }

    public function cadence(): BelongsTo
    {
        return $this->belongsTo(Cadence::class);
    }
}
