<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['company_id', 'name', 'email', 'role', 'active', 'google_id', 'microsoft_id', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use BelongsToCompany, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->active && in_array($this->role, [UserRole::Admin, UserRole::Manager], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isAgent(): bool
    {
        return $this->role === UserRole::Agent;
    }

    public function listAssignments(): HasMany
    {
        return $this->hasMany(ListAssignment::class);
    }

    public function leadClaims(): HasMany
    {
        return $this->hasMany(LeadClaim::class);
    }

    public function ownedCallbacks(): HasMany
    {
        return $this->hasMany(Lead::class, 'callback_owner_id');
    }

    public function canCall(): bool
    {
        return $this->active && $this->listAssignments()->exists();
    }
}
