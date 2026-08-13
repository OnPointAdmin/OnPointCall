<?php

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;

class PasswordResetUrlBuilder
{
    public function build(User $user, string $token): string
    {
        if (PasswordResetContext::surface() === PasswordResetContext::SurfaceAgent) {
            return url(route('agent.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false));
        }

        return Filament::getPanel('admin')->getResetPasswordUrl($token, $user);
    }
}
