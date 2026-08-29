<?php

namespace App\Models;

use App\Enums\DispositionButtonGroup;
use App\Enums\DispositionColor;
use App\Enums\DispositionOutcome;
use App\Enums\DispositionReportGroup;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DispositionDefinition extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $table = 'dispositions';

    protected $fillable = [
        'company_id',
        'slug',
        'label',
        'sort_order',
        'active',
        'is_system',
        'outcome',
        'increments_attempt',
        'requires_reason',
        'button_group',
        'color',
        'report_group',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
            'is_system' => 'boolean',
            'outcome' => DispositionOutcome::class,
            'increments_attempt' => 'boolean',
            'requires_reason' => 'boolean',
            'button_group' => DispositionButtonGroup::class,
            'color' => DispositionColor::class,
            'report_group' => DispositionReportGroup::class,
        ];
    }

    /**
     * @param  Builder<DispositionDefinition>  $query
     * @return Builder<DispositionDefinition>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @return Collection<string, DispositionDefinition>
     */
    public static function indexedForCompany(int $companyId): Collection
    {
        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->keyBy('slug');
    }

    /**
     * @return array<string, string> slug => label
     */
    public static function filterOptions(int $companyId): array
    {
        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label', 'slug')
            ->all();
    }

    public static function labelForSlug(int $companyId, string $slug): ?string
    {
        $label = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('slug', $slug)
            ->value('label');

        return is_string($label) && $label !== '' ? $label : null;
    }

    public static function findBySlug(int $companyId, string $slug): ?self
    {
        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('slug', $slug)
            ->first();
    }

    public static function pillClassesForSlug(int $companyId, string $slug): string
    {
        $definition = static::findBySlug($companyId, $slug);

        if ($definition?->color instanceof DispositionColor) {
            return $definition->color->pillClasses();
        }

        return DispositionColor::Slate->pillClasses();
    }
}
