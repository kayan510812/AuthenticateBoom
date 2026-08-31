<x-layout title="Create account">
    <div class="card">
        <h1>Create your account</h1>
        <p class="sub">Register with an email and password, or use your GitHub or Google account.</p>

        @if ($errors->has('email') && ! old('email'))
            <div class="alert error">{{ $errors->first('email') }}</div>
        @endif

        <x-oauth-buttons action="Sign up with" />

        <div class="divider">or</div>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="field">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn">Create account</button>
        </form>

        <p class="foot">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
</x-layout>
