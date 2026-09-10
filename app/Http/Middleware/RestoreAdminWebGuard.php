<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestoreAdminWebGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            $agent = Auth::guard('agent')->user();

            if ($agent instanceof User && $agent->active && $agent->role->canAccessAdmin()) {
                Auth::guard('web')->login($agent);
            }
        }

        return $next($request);
    }
}
