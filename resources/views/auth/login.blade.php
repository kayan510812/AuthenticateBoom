<x-layout title="Sign in">
    <div class="card">
        <h1>Sign in</h1>
        <p class="sub">Use your email and password, or continue with GitHub or Google.</p>

        @if ($errors->any() && $errors->has('email'))
            <div class="alert error">{{ $errors->first('email') }}</div>
        @endif

        <x-oauth-buttons action="Continue with" />

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
