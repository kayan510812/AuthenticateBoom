<x-layout title="Two factor authentication" wide>
    <div class="card">
        <div class="row">
            <h1 style="margin:0">Two factor authentication</h1>
            <span class="badge {{ $enabled ? 'on' : 'off' }}">{{ $enabled ? 'Enabled' : 'Not enabled' }}</span>
        </div>

        @if ($enabled)
            <p class="sub" style="margin-top:8px">
                Signing in asks for a code from your authenticator app on top of your password or GitHub account.
            </p>

            @if ($recoveryCodes)
                <div class="alert warn">
                    Store these recovery codes somewhere safe. Each one signs you in once if you lose your phone.
                </div>
                <ul class="codes">
                    @foreach ($recoveryCodes as $code)
                        <li>{{ $code }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="stack">
                <form method="POST" action="{{ route('two-factor.recovery-codes') }}">
                    @csrf
                    <button type="submit" class="btn secondary">Generate new recovery codes</button>
                </form>

                @if ($required)
                    <p class="meta">Two factor authentication is required for GitHub accounts and cannot be turned off.</p>
                @else
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('DELETE')

                        @if (auth()->user()->hasPassword())
                            <div class="field">
                                <label for="password">Confirm your password to disable</label>
                                <input id="password" type="password" name="password" autocomplete="current-password">
                                @error('password') <p class="error">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <div class="field">
                                <label for="code">Enter a current code to disable</label>
                                <input id="code" type="text" name="code" inputmode="numeric" maxlength="6">
                                @error('code') <p class="error">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <button type="submit" class="btn danger">Disable two factor authentication</button>
                    </form>
                @endif

                <p class="foot"><a href="{{ route('dashboard') }}">Back to dashboard</a></p>
            </div>
        @else
            <p class="sub" style="margin-top:8px">
                Scan the QR code with Google Authenticator, 1Password, Authy or any other TOTP app,
                then confirm with the 6 digit code it shows.
            </p>

            @if ($required)
                <div class="alert warn">
                    Your account signs in with GitHub, so two factor authentication is required before you can continue.
                </div>
            @endif

            @if ($qrCode)
                <div class="qr">{!! $qrCode !!}</div>
            @else
                <div class="alert warn">Could not render the QR code. Add the key below to your app manually.</div>
            @endif

            <h2>Cannot scan the code?</h2>
            <p class="meta">Enter this setup key by hand (type: time based):</p>
            <code class="secret">{{ trim(chunk_split($secret, 4, ' ')) }}</code>

            <form method="POST" action="{{ route('two-factor.confirm') }}" style="margin-top:22px">
                @csrf

                <div class="field">
                    <label for="code">Code from your app</label>
                    <input id="code" class="code-input" type="text" name="code" inputmode="numeric"
                           autocomplete="one-time-code" maxlength="6" required autofocus>
                    @error('code') <p class="error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn">Confirm and enable</button>
            </form>

            <form method="POST" action="{{ route('two-factor.reset') }}" class="foot">
                @csrf
                <button type="submit" class="btn link">Generate a different secret</button>
            </form>
        @endif
    </div>
</x-layout>
