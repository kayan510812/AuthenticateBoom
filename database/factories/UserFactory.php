<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A user that finished the two factor enrollment.
     */
    public function withTwoFactor(?string $secret = null): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => $secret ?? app(Google2FA::class)->generateSecretKey(32),
            'two_factor_recovery_codes' => app(TwoFactorAuthenticator::class)->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * A user that signed up through GitHub and has no local password.
     */
    public function github(string $githubId = '1001'): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'github_id' => $githubId,
            'github_nickname' => 'octocat',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4',
        ]);
    }

    /**
     * A user that signed up through Google and has no local password.
     */
    public function google(string $googleId = '2001'): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'google_id' => $googleId,
            'google_nickname' => null,
            'avatar_url' => 'https://lh3.googleusercontent.com/a/default-user',
        ]);
    }
}
