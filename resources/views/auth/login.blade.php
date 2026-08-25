<x-layout title="Sign in">
    <div class="card">
        <h1>Sign in</h1>
        <p class="sub">Use your email and password, or continue with GitHub.</p>

        @if ($errors->any() && $errors->has('email'))
            <div class="alert error">{{ $errors->first('email') }}</div>
        @endif

        <a class="btn github" href="{{ route('github.redirect') }}">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82a7.4 7.4 0 0 1 2-.27c.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z"/>
            </svg>
            Continue with GitHub
        </a>

        <div class="divider">or</div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>

            <label class="row checkbox">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                Remember me on this device
            </label>

            <button type="submit" class="btn">Sign in</button>
        </form>

        <p class="foot">No account yet? <a href="{{ route('register') }}">Create one</a></p>
    </div>
</x-layout>
