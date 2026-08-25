<x-layout title="Two factor authentication">
    <div class="card">
        <h1>Two factor authentication</h1>
        <p class="sub">
            @if ($via === 'github')
                GitHub confirmed who you are. Now enter the 6 digit code from your authenticator app.
            @else
                Enter the 6 digit code from your authenticator app to finish signing in.
            @endif
        </p>

        <form method="POST" action="{{ route('two-factor.challenge') }}">
            @csrf

            <div class="field">
                <label for="code">Authentication code</label>
                <input id="code" class="code-input" type="text" name="code" inputmode="numeric"
                       autocomplete="one-time-code" maxlength="6" required autofocus>
                @error('code') <p class="error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn">Verify and sign in</button>
        </form>

        @if ($hasRecoveryCodes)
            <div class="divider">lost your phone?</div>

            <form method="POST" action="{{ route('two-factor.challenge') }}">
                @csrf

                <div class="field">
                    <label for="recovery_code">Recovery code</label>
                    <input id="recovery_code" type="text" name="recovery_code" autocomplete="one-time-code">
                    @error('recovery_code') <p class="error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn secondary">Use recovery code</button>
            </form>
        @endif

        <form method="POST" action="{{ route('two-factor.cancel') }}" class="foot">
            @csrf
            <button type="submit" class="btn link">Cancel and sign in as someone else</button>
        </form>
    </div>
</x-layout>
