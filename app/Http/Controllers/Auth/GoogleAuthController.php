<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\AuthenticatesWithTwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    use AuthenticatesWithTwoFactor;

    /**
     * Send the user to Google's consent screen. The driver already asks for
     * openid/profile/email, which is everything we store.
     */
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Google sends the user back here with a short lived code.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign in was cancelled.',
            ]);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed', ['exception' => $e]);

            return redirect()->route('login')->withErrors([
                'email' => 'Could not complete the Google sign in. Please try again.',
            ]);
        }

        // Google always returns an address for the email scope; without one we
        // have nothing to match or create an account on.
        if (! $googleUser->getEmail()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google did not share an email address for this account.',
            ]);
        }

        $user = $this->findOrCreateUser($googleUser);

        return $this->loginOrChallenge($request, $user, remember: true, via: 'google');
    }

    /**
     * Match on the Google id first, then on the email so an existing password
     * or GitHub account gets linked instead of duplicated.
     */
    private function findOrCreateUser(\Laravel\Socialite\Contracts\User $googleUser): User
    {
        $email = $googleUser->getEmail();

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first();

        if (! $user) {
            $user = new User([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
            ]);

            $user->email_verified_at = now();
        }

        // avatar_url is shared with GitHub: the provider that signed in last wins.
        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'google_nickname' => $googleUser->getNickname(),
            'avatar_url' => $googleUser->getAvatar(),
        ])->save();

        return $user;
    }
}
