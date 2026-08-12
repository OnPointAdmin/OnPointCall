<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\RecordsSettingsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadTypeDefinition extends Model
{
    use BelongsToCompany, RecordsSettingsChanges;

    protected $table = 'lead_types';

    protected $fillable = [
        'company_id',
        'slug',
        'name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return array<string, string> slug => name
     */
    public static function activeOptions(): array
    {
        return static::query()
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * @return array<string, string> slug => name
     */
    public static function allOptions(): array
    {
        return static::query()
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();
    }

    public static function createFromName(string $name, ?string $slug = null, bool $active = true): self
    {
        $resolvedSlug = $slug !== null && $slug !== ''
            ? Str::slug($slug)
            : Str::slug($name);

        if ($resolvedSlug === '') {
            $resolvedSlug = 'lead-type-'.Str::lower(Str::random(6));
        }

        return static::query()->firstOrCreate(
            ['slug' => $resolvedSlug],
            [
                'name' => $name,
                'active' => $active,
            ],
        );
    }
}
