<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Auth\Impersonation;
use Illuminate\Http\RedirectResponse;

class ImpersonationController extends Controller
{
    public function stop(Impersonation $impersonation): RedirectResponse
    {
        if (! $impersonation->isActive()) {
            return redirect()->route('agent.workspace');
        }

        return $impersonation->stop();
    }
}
