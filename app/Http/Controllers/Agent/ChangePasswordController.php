<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    private const GUARD = 'agent';

    public function show(): View
    {
        return view('agent.change-password', [
            'forced' => Auth::guard(self::GUARD)->user()->mustChangePassword(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::guard(self::GUARD)->user();
        $forced = $user->mustChangePassword();

        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if (! $forced) {
            $rules['current_password'] = ['required', 'current_password:'.self::GUARD];
        }

        $validated = $request->validate($rules);

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => $forced
                    ? 'Choose a different password from your temporary password.'
                    : 'Choose a different password from your current password.',
            ]);
        }

        $user->password = $validated['password'];
        $user->must_change_password = false;
        if ($forced && $user->email_verified_at === null) {
            $user->email_verified_at = now();
        }
        $user->save();

        $request->session()->forget('url.intended');
        $request->session()->put([
            'password_hash_'.self::GUARD => $user->getAuthPassword(),
        ]);

        return redirect()
            ->route('agent.workspace')
            ->with('status', 'Your password has been updated.');
    }
}
