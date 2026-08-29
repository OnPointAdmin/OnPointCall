<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Database\Seeders\DispositionSeeder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'active',
        'salesforce_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function callingLists(): HasMany
    {
        return $this->hasMany(CallingList::class);
    }

    public function appSettings(): HasOne
    {
        return $this->hasOne(AppSetting::class);
    }

    public static function booted(): void
    {
        static::created(function (Company $company): void {
            (new DispositionSeeder)->run($company->id);
        });
    }
}
