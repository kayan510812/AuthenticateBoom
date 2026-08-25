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

class GithubOAuthController extends Controller
{
    use AuthenticatesWithTwoFactor;

    /**
     * Send the user to GitHub's consent screen.
     */
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email'])
            ->redirect();
    }

    /**
     * GitHub sends the user back here with a short lived code.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors([
                'email' => 'GitHub sign in was cancelled.',
            ]);
        }

        try {
            $githubUser = Socialite::driver('github')->user();
        } catch (Throwable $e) {
            Log::warning('GitHub OAuth callback failed', ['exception' => $e]);

            return redirect()->route('login')->withErrors([
                'email' => 'Could not complete the GitHub sign in. Please try again.',
            ]);
        }

        $user = $this->findOrCreateUser($githubUser);

        return $this->loginOrChallenge($request, $user, remember: true, via: 'github');
    }

    /**
     * Match on the GitHub id first, then on a verified email so an existing
     * password account gets linked instead of duplicated.
     */
    private function findOrCreateUser(\Laravel\Socialite\Contracts\User $githubUser): User
    {
        $email = $githubUser->getEmail()
            ?: $githubUser->getNickname().'@users.noreply.github.com';

        $user = User::where('github_id', $githubUser->getId())->first()
            ?? User::where('email', $email)->first();

        if (! $user) {
            $user = new User([
                'name' => $githubUser->getName() ?: $githubUser->getNickname() ?: Str::before($email, '@'),
                'email' => $email,
            ]);

            $user->email_verified_at = now();
        }

        $user->forceFill([
            'github_id' => $githubUser->getId(),
            'github_nickname' => $githubUser->getNickname(),
            'avatar_url' => $githubUser->getAvatar(),
        ])->save();

        return $user;
    }
}
