<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait AuthenticatesWithTwoFactor
{
    /**
     * Session key holding the id of the half authenticated user.
     */
    public const CHALLENGE_USER = 'auth.2fa.user_id';

    public const CHALLENGE_REMEMBER = 'auth.2fa.remember';

    public const CHALLENGE_EXPIRES = 'auth.2fa.expires_at';

    public const CHALLENGE_VIA = 'auth.2fa.via';

    /**
     * First factor succeeded. Either finish the login, or park the user in the
     * two factor challenge without ever touching the auth guard.
     */
    protected function loginOrChallenge(Request $request, User $user, bool $remember = false, string $via = 'password'): RedirectResponse
    {
        if ($user->hasTwoFactorEnabled()) {
            // Nothing is authenticated yet; only a pending id lives in the session.
            $request->session()->put([
                self::CHALLENGE_USER => $user->getKey(),
                self::CHALLENGE_REMEMBER => $remember,
                self::CHALLENGE_EXPIRES => now()->addSeconds(config('mfa.challenge_lifetime'))->getTimestamp(),
                self::CHALLENGE_VIA => $via,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return $this->redirectAfterLogin($request, $via);
    }

    /**
     * Where a fully authenticated user lands.
     */
    protected function redirectAfterLogin(Request $request, string $via = 'password'): RedirectResponse
    {
        $user = $request->user();

        if ($via === 'github' && config('mfa.enforce_for_oauth') && $user && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup')
                ->with('status', 'Set up two factor authentication to finish securing your account.');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * The user waiting on the challenge, or null when there is none / expired.
     */
    protected function challengedUser(Request $request): ?User
    {
        $id = $request->session()->get(self::CHALLENGE_USER);
        $expires = $request->session()->get(self::CHALLENGE_EXPIRES);

        if (! $id || ! $expires || $expires < now()->getTimestamp()) {
            return null;
        }

        return User::find($id);
    }

    protected function forgetChallenge(Request $request): void
    {
        $request->session()->forget([
            self::CHALLENGE_USER,
            self::CHALLENGE_REMEMBER,
            self::CHALLENGE_EXPIRES,
            self::CHALLENGE_VIA,
        ]);
    }
}
