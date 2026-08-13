<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const GUARD = 'agent';

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard(self::GUARD)->check() && Auth::guard(self::GUARD)->user()->canCall()) {
            return redirect()->route('agent.workspace');
        }

        return view('agent.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard(self::GUARD)->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid credentials.']);
        }

        $request->session()->regenerate();

        $user = Auth::guard(self::GUARD)->user();

        if (! $user->active) {
            Auth::guard(self::GUARD)->logout();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Your account is deactivated.']);
        }

        if (! $user->canCall()) {
            Auth::guard(self::GUARD)->logout();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'You are not assigned to any calling lists.']);
        }

        return redirect()->intended(route('agent.workspace'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard(self::GUARD)->logout();

        // Keep the Filament (web) session intact if present.
        $request->session()->regenerate();

        return redirect()->route('agent.login');
    }
}
