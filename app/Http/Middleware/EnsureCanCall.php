<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanCall
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('agent') ?? $request->user();

        if (! $user instanceof User || ! $user->active) {
            abort(403, 'You are not assigned to any calling lists.');
        }

        if ($user->canCall() || $user->role->canAccessAdmin()) {
            return $next($request);
        }

        abort(403, 'You are not assigned to any calling lists.');
    }
}
