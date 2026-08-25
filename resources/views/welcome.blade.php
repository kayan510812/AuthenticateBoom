<x-layout title="Welcome">
    <div class="card">
        <h1>{{ config('app.name') }}</h1>
        <p class="sub">Sign in with an email and password or with GitHub, protected by a TOTP second factor.</p>

        @auth
            <a class="btn" href="{{ route('dashboard') }}">Go to dashboard</a>
        @else
            <div class="stack">
                <a class="btn" href="{{ route('login') }}">Sign in</a>
                <a class="btn secondary" href="{{ route('register') }}">Create an account</a>
            </div>
        @endauth
    </div>
</x-layout>
