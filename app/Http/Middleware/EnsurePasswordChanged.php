<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->mustChangePassword()) {
            return $next($request);
        }

        if ($this->isAllowedWhilePasswordChangeRequired($request)) {
            return $next($request);
        }

        if ($this->isAdminRequest($request)) {
            return redirect()->to(Filament::getProfileUrl() ?? url('/admin/profile'));
        }

        return redirect()->route('agent.password.change');
    }

    private function isAllowedWhilePasswordChangeRequired(Request $request): bool
    {
        return $request->routeIs(
            'agent.password.change',
            'agent.password.change.update',
            'agent.logout',
            'filament.admin.auth.profile',
            'filament.admin.auth.logout',
        );
    }

    private function isAdminRequest(Request $request): bool
    {
        return $request->routeIs('filament.admin.*')
            || $request->is('admin')
            || $request->is('admin/*');
    }
}
