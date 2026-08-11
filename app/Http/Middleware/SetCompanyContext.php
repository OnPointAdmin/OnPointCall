<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            CompanyContext::set($user->company_id);
        }

        try {
            return $next($request);
        } finally {
            CompanyContext::clear();
        }
    }
}
