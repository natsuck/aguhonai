<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if (
            ! $user->email_verification_code_hash
            || ! $user->email_verification_code_expires_at
            || $user->email_verification_code_expires_at->isPast()
        ) {
            return back()->withErrors([
                'code' => 'Your verification code has expired. Request a new code and try again.',
            ]);
        }

        if ($user->email_verification_code_attempts >= 5) {
            return back()->withErrors([
                'code' => 'Too many incorrect attempts. Request a new verification code.',
            ]);
        }

        if (! Hash::check($validated['code'], $user->email_verification_code_hash)) {
            $user->increment('email_verification_code_attempts');

            return back()->withErrors([
                'code' => $user->fresh()->email_verification_code_attempts >= 5
                    ? 'Too many incorrect attempts. Request a new verification code.'
                    : 'That code is incorrect. Please check the email and try again.',
            ]);
        }

        $user->forceFill([
            'email_verification_code_hash' => null,
            'email_verification_code_expires_at' => null,
            'email_verification_code_attempts' => 0,
        ])->save();

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
