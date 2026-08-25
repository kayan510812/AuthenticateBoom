@props(['title' => config('app.name'), 'wide' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} &middot; {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
        <nav>
            @auth
                <span class="who">
                    @if (auth()->user()->avatar_url)
                        <img class="avatar" src="{{ auth()->user()->avatar_url }}" alt="">
                    @endif
                    {{ auth()->user()->name }}
                </span>
                <a href="{{ route('two-factor.setup') }}">Security</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn link">Sign out</button>
                </form>
            @else
                <a href="{{ route('login') }}">Sign in</a>
                <a href="{{ route('register') }}">Create account</a>
            @endauth
        </nav>
    </header>

    <main class="shell {{ $wide ? 'wide' : '' }}">
        @if (session('status'))
            <div class="alert status">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
