<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorSettingsController extends Controller
{
    private const PENDING_SECRET = 'two_factor.pending_secret';

    public function __construct(private TwoFactorAuthenticator $authenticator) {}

    /**
     * Enrollment screen: QR code + confirmation, or the "enabled" state.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return view('auth.two-factor-settings', [
                'enabled' => true,
                'recoveryCodes' => $request->session()->get('two_factor.recovery_codes'),
                'required' => $this->isRequired($user),
            ]);
        }

        // Keep the same secret across page reloads until it is confirmed.
        $secret = $request->session()->get(self::PENDING_SECRET);

        if (! $secret) {
            $secret = $this->authenticator->generateSecret();
            $request->session()->put(self::PENDING_SECRET, $secret);
        }

        return view('auth.two-factor-settings', [
            'enabled' => false,
            'secret' => $secret,
            'qrCode' => $this->authenticator->qrCodeSvg($user, $secret),
            'otpauthUrl' => $this->authenticator->otpauthUrl($user, $secret),
            'required' => $this->isRequired($user),
        ]);
    }

    /**
     * Start over with a fresh secret.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PENDING_SECRET);

        return redirect()->route('two-factor.setup');
    }

    /**
     * Confirm the authenticator app is in sync, then switch two factor on.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $secret = $request->session()->get(self::PENDING_SECRET);

        if (! $secret) {
            return redirect()->route('two-factor.setup')->withErrors([
                'code' => 'Your setup session expired. Scan the new QR code and try again.',
            ]);
        }

        if (! $this->authenticator->verifyAgainstSecret($secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Make sure your phone clock is correct and try the next code.',
            ]);
        }

        $recoveryCodes = $this->authenticator->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_timestamp' => null,
        ])->save();

        $request->session()->forget(self::PENDING_SECRET);

        return redirect()->route('two-factor.setup')
            ->with('two_factor.recovery_codes', $recoveryCodes)
            ->with('status', 'Two factor authentication is now enabled.');
    }

    /**
     * Issue a new set of recovery codes, invalidating the old ones.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasTwoFactorEnabled(), 403);

        $recoveryCodes = $this->authenticator->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $recoveryCodes])->save();

        return redirect()->route('two-factor.setup')
            ->with('two_factor.recovery_codes', $recoveryCodes)
            ->with('status', 'New recovery codes generated. The old ones no longer work.');
    }

    /**
     * Turn two factor off. Requires the account password, or a valid code for
     * accounts that only sign in through GitHub.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasTwoFactorEnabled(), 403);

        if ($this->isRequired($user)) {
            throw ValidationException::withMessages([
                'password' => 'Two factor authentication is required for GitHub accounts and cannot be disabled.',
            ]);
        }

        if ($user->hasPassword()) {
            $request->validate(['password' => ['required', 'current_password']]);
        } else {
            $request->validate(['code' => ['required', 'string']]);

            if (! $this->authenticator->verifyForUser($user, $request->string('code')->toString())) {
                throw ValidationException::withMessages(['code' => 'That code is not valid.']);
            }
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
        ])->save();

        return redirect()->route('two-factor.setup')
            ->with('status', 'Two factor authentication is disabled.');
    }

    /**
     * OAuth accounts must keep two factor on when enforcement is enabled.
     */
    private function isRequired($user): bool
    {
        return config('mfa.enforce_for_oauth')
            && (! is_null($user->github_id) || ! is_null($user->google_id));
    }
}
