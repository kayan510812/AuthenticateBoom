<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticatorTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorAuthenticator $authenticator;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2fa = app(Google2FA::class);
        $this->authenticator = new TwoFactorAuthenticator($this->google2fa);
    }

    public function test_it_generates_a_32_character_base32_secret(): void
    {
        $secret = $this->authenticator->generateSecret();

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_every_secret_is_different(): void
    {
        $this->assertNotSame(
            $this->authenticator->generateSecret(),
            $this->authenticator->generateSecret(),
        );
    }

    public function test_the_otpauth_url_carries_the_issuer_and_the_email(): void
    {
        config(['mfa.issuer' => 'AuthenticateBoom']);
        $user = User::factory()->make(['email' => 'kayan@example.com']);
        $secret = $this->authenticator->generateSecret();

        $url = $this->authenticator->otpauthUrl($user, $secret);

        $this->assertStringStartsWith('otpauth://totp/', $url);
        $this->assertStringContainsString('AuthenticateBoom', $url);
        $this->assertStringContainsString('kayan%40example.com', $url);
        $this->assertStringContainsString('secret='.$secret, $url);
    }

    public function test_it_renders_the_secret_as_an_inline_svg(): void
    {
        $svg = $this->authenticator->qrCodeSvg(
            User::factory()->make(),
            $this->authenticator->generateSecret(),
        );

        $this->assertNotNull($svg);
        $this->assertStringContainsString('<svg', $svg);
    }

    public function test_it_accepts_the_current_code_for_a_bare_secret(): void
    {
        $secret = $this->authenticator->generateSecret();

        $this->assertTrue($this->authenticator->verifyAgainstSecret(
            $secret,
            $this->google2fa->getCurrentOtp($secret),
        ));
    }

    public function test_it_ignores_whitespace_inside_a_code(): void
    {
        $secret = $this->authenticator->generateSecret();
        $code = $this->google2fa->getCurrentOtp($secret);

        $spaced = substr($code, 0, 3).' '.substr($code, 3);

        $this->assertTrue($this->authenticator->verifyAgainstSecret($secret, $spaced));
    }

    public function test_it_rejects_a_wrong_code_for_a_bare_secret(): void
    {
        $secret = $this->authenticator->generateSecret();
        $code = $this->google2fa->getCurrentOtp($secret);

        $this->assertFalse($this->authenticator->verifyAgainstSecret($secret, $code === '000000' ? '111111' : '000000'));
    }

    public function test_a_malformed_code_does_not_blow_up(): void
    {
        $this->assertFalse($this->authenticator->verifyAgainstSecret(
            $this->authenticator->generateSecret(),
            'not-a-code',
        ));
    }

    public function test_a_user_without_a_secret_never_verifies(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->authenticator->verifyForUser($user, '123456'));
    }

    public function test_a_valid_code_verifies_and_stores_the_time_slice(): void
    {
        $secret = $this->authenticator->generateSecret();
        $user = User::factory()->withTwoFactor($secret)->create();

        $this->assertTrue($this->authenticator->verifyForUser(
            $user,
            $this->google2fa->getCurrentOtp($secret),
        ));

        $this->assertNotNull($user->fresh()->two_factor_last_timestamp);
    }

    public function test_the_same_code_cannot_be_used_twice(): void
    {
        $secret = $this->authenticator->generateSecret();
        $user = User::factory()->withTwoFactor($secret)->create();
        $code = $this->google2fa->getCurrentOtp($secret);

        $this->assertTrue($this->authenticator->verifyForUser($user, $code));
        $this->assertFalse($this->authenticator->verifyForUser($user, $code));
    }

    public function test_a_wrong_code_leaves_the_time_slice_untouched(): void
    {
        $secret = $this->authenticator->generateSecret();
        $user = User::factory()->withTwoFactor($secret)->create();

        $this->assertFalse($this->authenticator->verifyForUser($user, '000000'));

        $this->assertNull($user->fresh()->two_factor_last_timestamp);
    }

    public function test_it_generates_the_configured_number_of_recovery_codes(): void
    {
        config(['mfa.recovery_codes' => 8]);

        $codes = $this->authenticator->generateRecoveryCodes();

        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}-[A-Z0-9]{10}$/', $code);
        }
    }

    public function test_a_recovery_code_works_once_and_is_then_gone(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $codes = $user->two_factor_recovery_codes;
        $code = $codes[0];

        $this->assertTrue($this->authenticator->useRecoveryCode($user, $code));

        $remaining = $user->fresh()->two_factor_recovery_codes;
        $this->assertCount(count($codes) - 1, $remaining);
        $this->assertNotContains($code, $remaining);

        $this->assertFalse($this->authenticator->useRecoveryCode($user->fresh(), $code));
    }

    public function test_a_recovery_code_is_matched_case_insensitively_and_trimmed(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $code = $user->two_factor_recovery_codes[0];

        $this->assertTrue($this->authenticator->useRecoveryCode($user, '  '.strtolower($code).' '));
    }

    public function test_an_unknown_recovery_code_is_rejected_and_keeps_the_set_intact(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->assertFalse($this->authenticator->useRecoveryCode($user, 'AAAAAAAAAA-BBBBBBBBBB'));

        $this->assertCount(
            count($user->two_factor_recovery_codes),
            $user->fresh()->two_factor_recovery_codes,
        );
    }
}
