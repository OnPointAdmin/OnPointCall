<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Support\PasswordResetContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showForgotPassword(): View
    {
        return view('agent.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        PasswordResetContext::useAgentSurface();

        try {
            $status = Password::sendResetLink($request->only('email'));

            Log::info('Agent password reset requested.', [
                'email' => $request->input('email'),
                'status' => $status,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Agent password reset email failed.', [
                'email' => $request->input('email'),
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'We could not send a reset email right now. Please try again later.']);
        } finally {
            PasswordResetContext::reset();
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Please wait before requesting another reset link.']);
        }

        return back()->with('status', $this->statusMessage());
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('agent.reset-password', [
            'token' => $token,
            'email' => $request->query('email', $request->old('email')),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('agent.login')
                ->with('status', 'Your password has been reset. You can sign in with your new password.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    private function statusMessage(): string
    {
        return 'If that email exists in our system, we sent a password reset link.';
    }
}
