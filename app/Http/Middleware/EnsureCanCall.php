<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanCall
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active || ! $user->canCall()) {
            abort(403, 'You are not assigned to any calling lists.');
        }

        return $next($request);
    }
}
