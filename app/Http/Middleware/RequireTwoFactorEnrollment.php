<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps GitHub users out of the application until they enrolled in two factor
 * authentication, when MFA_ENFORCE_FOR_OAUTH is on.
 */
class RequireTwoFactorEnrollment
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && config('mfa.enforce_for_oauth')
            && $user->github_id
            && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup')->with(
                'status',
                'Two factor authentication is required for GitHub accounts. Finish the setup to continue.'
            );
        }

        return $next($request);
    }
}
