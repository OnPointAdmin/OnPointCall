<?php

namespace App\Models;

use App\Enums\CadenceWaitUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CadenceAttemptGap extends Model
{
    protected $fillable = [
        'cadence_id',
        'after_attempt',
        'wait_value',
        'wait_unit',
    ];

    protected function casts(): array
    {
        return [
            'after_attempt' => 'integer',
            'wait_value' => 'integer',
            'wait_unit' => CadenceWaitUnit::class,
        ];
    }

    public function cadence(): BelongsTo
    {
        return $this->belongsTo(Cadence::class);
    }
}
