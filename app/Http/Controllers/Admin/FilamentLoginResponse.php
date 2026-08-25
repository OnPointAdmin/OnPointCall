<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class FilamentLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && $user->mustChangePassword()) {
            return redirect()->to(Filament::getProfileUrl() ?? Filament::getUrl());
        }

        return redirect()->intended(Filament::getUrl());
    }
}
