<?php

namespace App\Models;

use App\Enums\Disposition;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string, string|Disposition>
     */
    protected function disposition(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): string => (string) $value,
            set: fn (mixed $value): string => $value instanceof Disposition ? $value->value : (string) $value,
        );
    }

    /**
     * @param  Builder<DispositionReason>  $query
     * @return Builder<DispositionReason>
     */
    public function scopeActiveForSlug(Builder $query, string $slug): Builder
    {
        return $query
            ->where('disposition', $slug)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('label');
    }
}
