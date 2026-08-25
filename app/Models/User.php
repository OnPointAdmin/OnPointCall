<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Mail\PasswordResetMail;
use App\Models\Concerns\BelongsToCompany;
use App\Support\PasswordResetContext;
use App\Support\PasswordResetUrlBuilder;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

#[Fillable(['company_id', 'name', 'email', 'role', 'active', 'google_id', 'microsoft_id', 'salesforce_id', 'password', 'must_change_password'])]
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
            'must_change_password' => 'boolean',
        ];
    }

    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password;
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

    public function sendPasswordResetNotification($token): void
    {
        if (! $this->active) {
            Log::info('Password reset skipped for inactive user.', ['email' => $this->email]);

            return;
        }

        $resetUrl = app(PasswordResetUrlBuilder::class)->build($this, $token);

        Mail::to($this->email)->send(new PasswordResetMail($this, $resetUrl));

        Log::info('Password reset email sent.', [
            'email' => $this->email,
            'surface' => PasswordResetContext::surface(),
        ]);
    }
}
