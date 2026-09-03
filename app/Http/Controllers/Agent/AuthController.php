<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const AGENT_GUARD = 'agent';

    private const WEB_GUARD = 'web';

    public function showLogin(): View|RedirectResponse
    {
        $user = $this->authenticatedUser();

        if ($user) {
            return $this->redirectAuthenticatedUser($user);
        }

        return view('agent.login');
    }

    public function showChoose(): View|RedirectResponse
    {
        $user = $this->authenticatedUser();

        if (! $user?->role->canAccessAdmin()) {
            return redirect()->route('login');
        }

        return view('agent.choose');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard(self::AGENT_GUARD)->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid credentials.']);
        }

        $request->session()->regenerate();

        $user = Auth::guard(self::AGENT_GUARD)->user();

        if (! $user->active) {
            $this->logoutBoth();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Your account is deactivated.']);
        }

        if ($user->isAgent() && ! $user->canCall()) {
            $this->logoutBoth();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'You are not assigned to any calling lists.']);
        }

        $remember = $request->boolean('remember');

        if ($user->role->canAccessAdmin()) {
            Auth::guard(self::WEB_GUARD)->login($user, $remember);
        } else {
            Auth::guard(self::WEB_GUARD)->logout();
        }

        return $this->redirectAfterLogin($request, $user);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard(self::AGENT_GUARD)->logout();

        // Keep the Filament (web) session intact if present.
        $request->session()->regenerate();

        return redirect()->route('login');
    }

    private function authenticatedUser(): ?User
    {
        return Auth::guard(self::WEB_GUARD)->user()
            ?? Auth::guard(self::AGENT_GUARD)->user();
    }

    private function redirectAuthenticatedUser(User $user): RedirectResponse
    {
        if ($user->role->canAccessAdmin()) {
            return redirect()->route('choose');
        }

        if ($user->canCall()) {
            if ($user->mustChangePassword()) {
                return redirect()->route('agent.password.change');
            }

            return redirect()->route('agent.workspace');
        }

        $this->logoutBoth();

        return redirect()->route('login');
    }

    private function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        if ($intended && $this->canAccessIntendedUrl($user, $intended)) {
            return redirect()->to($intended);
        }

        if ($user->role->canAccessAdmin()) {
            return redirect()->route('choose');
        }

        if ($user->mustChangePassword()) {
            return redirect()->route('agent.password.change');
        }

        return redirect()->route('agent.workspace');
    }

    private function canAccessIntendedUrl(User $user, string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (str_starts_with($path, '/admin')) {
            return $user->role->canAccessAdmin();
        }

        if (str_starts_with($path, '/agent')) {
            return $user->canCall() || $user->role->canAccessAdmin();
        }

        return false;
    }

    private function logoutBoth(): void
    {
        Auth::guard(self::AGENT_GUARD)->logout();
        Auth::guard(self::WEB_GUARD)->logout();
    }
}
