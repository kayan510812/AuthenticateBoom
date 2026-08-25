<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Issuer
    |--------------------------------------------------------------------------
    |
    | Shown as the account label inside authenticator apps such as Google
    | Authenticator, 1Password or Authy.
    |
    */

    'issuer' => env('MFA_ISSUER', env('APP_NAME', 'Laravel')),

    /*
    |--------------------------------------------------------------------------
    | Force enrollment for GitHub sign ins
    |--------------------------------------------------------------------------
    |
    | When true, a user that signed in through GitHub without two factor
    | authentication is redirected to the setup screen and cannot use the
    | rest of the application until an authenticator app is confirmed.
    |
    */

    'enforce_for_oauth' => (bool) env('MFA_ENFORCE_FOR_OAUTH', true),

    /*
    |--------------------------------------------------------------------------
    | Verification window
    |--------------------------------------------------------------------------
    |
    | Number of 30 second slices before/after the current one that are still
    | accepted, to allow for clock drift on the phone.
    |
    */

    'window' => (int) env('MFA_WINDOW', 1),

    /*
    |--------------------------------------------------------------------------
    | Challenge lifetime
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) the pending two factor challenge stays valid after
    | the password / GitHub step succeeded.
    |
    */

    'challenge_lifetime' => (int) env('MFA_CHALLENGE_LIFETIME', 300),

    /*
    |--------------------------------------------------------------------------
    | Recovery codes
    |--------------------------------------------------------------------------
    */

    'recovery_codes' => 8,

];
