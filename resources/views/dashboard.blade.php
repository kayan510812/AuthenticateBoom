<x-layout title="Dashboard" wide>
    <div class="card">
        <h1>You are signed in</h1>
        <p class="sub">This page is only reachable once every authentication factor passed.</p>

        <figure class="hero">
            <img src="{{ asset('images/lapeace-kaicenat.jpg') }}" alt="La Peace">
        </figure>

        <h2>Account</h2>
        <div class="stack">
            <p class="meta">Name: <strong>{{ auth()->user()->name }}</strong></p>
            <p class="meta">Email: <strong>{{ auth()->user()->email }}</strong></p>
            <p class="meta">
                Sign in method:
                <strong>
                    @if (auth()->user()->github_id && auth()->user()->hasPassword())
                        GitHub ({{ auth()->user()->github_nickname }}) and password
                    @elseif (auth()->user()->github_id)
                        GitHub ({{ auth()->user()->github_nickname }})
                    @else
                        Email and password
                    @endif
                </strong>
            </p>
            <p class="meta">
                Two factor:
                <span class="badge {{ auth()->user()->hasTwoFactorEnabled() ? 'on' : 'off' }}">
                    {{ auth()->user()->hasTwoFactorEnabled() ? 'Enabled' : 'Not enabled' }}
                </span>
            </p>
        </div>

        <p class="foot"><a href="{{ route('two-factor.setup') }}">Manage two factor authentication</a></p>
    </div>
</x-layout>
