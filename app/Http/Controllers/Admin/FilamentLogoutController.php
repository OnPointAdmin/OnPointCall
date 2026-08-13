<?php

namespace App\Http\Controllers\Admin;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;

class FilamentLogoutController
{
    public function __invoke(): LogoutResponse
    {
        Filament::auth()->logout();

        // Keep the agent session intact if present.
        session()->regenerate();

        return app(LogoutResponse::class);
    }
}
