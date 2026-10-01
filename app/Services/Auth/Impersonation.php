<?php

namespace App\Services\Auth;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    private const AGENT_GUARD = 'agent';

    public function isActive(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public function impersonator(): ?User
    {
        $id = session(self::SESSION_KEY);

        if (! $id) {
            return null;
        }

        return User::query()->find($id);
    }

    public function canStart(User $actor, User $target): bool
    {
        try {
            $this->assertCanStart($actor, $target);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function start(User $actor, User $target): void
    {
        $this->assertCanStart($actor, $target);

        if (! $this->isActive()) {
            session()->put(self::SESSION_KEY, $actor->id);
        }

        Auth::guard(self::AGENT_GUARD)->login($target);

        Log::info('Admin impersonation started.', [
            'impersonator_id' => session(self::SESSION_KEY),
            'impersonated_user_id' => $target->id,
        ]);
    }

    public function stop(): RedirectResponse
    {
        $impersonator = $this->impersonator();
        $impersonatedId = Auth::guard(self::AGENT_GUARD)->id();

        if (! $impersonator instanceof User || ! $impersonator->active) {
            session()->forget(self::SESSION_KEY);
            Auth::guard(self::AGENT_GUARD)->logout();

            return redirect()->route('login');
        }

        Log::info('Admin impersonation stopped.', [
            'impersonator_id' => $impersonator->id,
            'impersonated_user_id' => $impersonatedId,
        ]);

        session()->forget(self::SESSION_KEY);
        Auth::guard(self::AGENT_GUARD)->login($impersonator);

        return redirect()->to(UserResource::getUrl('index'));
    }

    private function assertCanStart(User $actor, User $target): void
    {
        if (! $actor->isAdmin() || ! $actor->active) {
            throw new AuthorizationException('Only active admins can log in as another user.');
        }

        if ($actor->id === $target->id) {
            throw new AuthorizationException('You cannot log in as yourself.');
        }

        if (! $target->active) {
            throw new AuthorizationException('That user is deactivated.');
        }

        if ($actor->company_id !== $target->company_id) {
            throw new AuthorizationException('That user is in another company.');
        }
    }
}
