<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\AuthenticatesWithTwoFactor;
use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    use AuthenticatesWithTwoFactor;

    public function __construct(private TwoFactorAuthenticator $authenticator) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->challengedUser($request);

        if (! $user) {
            $this->forgetChallenge($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Your sign in attempt expired. Please start again.',
            ]);
        }

        return view('auth.two-factor-challenge', [
            'via' => $request->session()->get(self::CHALLENGE_VIA, 'password'),
            'hasRecoveryCodes' => ! empty($user->two_factor_recovery_codes),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->challengedUser($request);

        if (! $user) {
            $this->forgetChallenge($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Your sign in attempt expired. Please start again.',
            ]);
        }

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $throttleKey = 'two-factor:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', [
                    'seconds' => $seconds = RateLimiter::availableIn($throttleKey),
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $passed = $request->filled('recovery_code')
            ? $this->authenticator->useRecoveryCode($user, $request->string('recovery_code')->toString())
            : $this->authenticator->verifyForUser($user, $request->string('code')->toString());

        if (! $passed) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                $request->filled('recovery_code') ? 'recovery_code' : 'code' => $request->filled('recovery_code')
                    ? 'That recovery code is not valid.'
                    : 'That code is not valid. Check your authenticator app and try again.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) $request->session()->get(self::CHALLENGE_REMEMBER, false);
        $via = $request->session()->get(self::CHALLENGE_VIA, 'password');

        $this->forgetChallenge($request);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return $this->redirectAfterLogin($request, $via);
    }

    /**
     * Abandon a pending challenge (the "use another account" link).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->forgetChallenge($request);

        return redirect()->route('login');
    }
}
