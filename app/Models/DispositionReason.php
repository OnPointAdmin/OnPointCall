<?php

namespace App\Models;

use App\Enums\Disposition;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DispositionReason extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $fillable = [
        'company_id',
        'disposition',
        'label',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'disposition' => Disposition::class,
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<DispositionReason>  $query
     * @return Builder<DispositionReason>
     */
    public function scopeActiveFor(Builder $query, Disposition $disposition): Builder
    {
        return $query
            ->where('disposition', $disposition->value)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('label');
    }
}
