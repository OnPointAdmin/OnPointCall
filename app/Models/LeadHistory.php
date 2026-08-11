<?php

namespace App\Models;

use App\Enums\LeadHistoryType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadHistory extends Model
{
    use BelongsToCompany;

    protected $table = 'lead_history';

    protected $fillable = [
        'company_id',
        'lead_id',
        'actor_id',
        'event_type',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => LeadHistoryType::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
