<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * Everything TOTP: secrets, QR codes, code verification and recovery codes.
 */
class TwoFactorAuthenticator
{
    public function __construct(private Google2FA $google2fa) {}

    /**
     * Fresh base32 secret for a new enrollment.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * otpauth:// URI that authenticator apps understand.
     */
    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('mfa.issuer'),
            $user->email,
            $secret,
        );
    }

    /**
     * Inline SVG for the otpauth URI, or null when QR rendering is unavailable
     * (the setup screen then falls back to the typed secret).
     */
    public function qrCodeSvg(User $user, string $secret, int $size = 232): ?string
    {
        try {
            $writer = new Writer(
                new ImageRenderer(new RendererStyle($size, 0), new SvgImageBackEnd)
            );

            return $writer->writeString($this->otpauthUrl($user, $secret));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Check a 6 digit code against the given secret without touching the user.
     * Used while confirming an enrollment, where nothing is stored yet.
     */
    public function verifyAgainstSecret(string $secret, string $code): bool
    {
        try {
            return (bool) $this->google2fa->verifyKey($secret, $this->normalize($code), config('mfa.window'));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check a 6 digit code for a user with two factor enabled. A code is only
     * accepted once: the time slice is stored so replays are rejected.
     */
    public function verifyForUser(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        try {
            // Passing a non-null old timestamp makes google2fa return the time
            // slice the code belongs to instead of a plain true.
            $timestamp = $this->google2fa->verifyKeyNewer(
                $user->two_factor_secret,
                $this->normalize($code),
                (int) ($user->two_factor_last_timestamp ?? 0),
                config('mfa.window'),
            );
        } catch (Throwable) {
            return false;
        }

        if ($timestamp === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_timestamp' => (int) $timestamp])->save();

        return true;
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, config('mfa.recovery_codes')))
            ->map(fn () => Str::upper(Str::random(10).'-'.Str::random(10)))
            ->all();
    }

    /**
     * Consume a recovery code. Returns true when it matched, and the code is
     * removed from the user so it can never be used again.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $given = Str::upper(trim($code));

        $match = null;

        foreach ($codes as $stored) {
            if (hash_equals($stored, $given)) {
                $match = $stored;
                break;
            }
        }

        if ($match === null) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_diff($codes, [$match])),
        ])->save();

        return true;
    }

    private function normalize(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?? $code;
    }
}
