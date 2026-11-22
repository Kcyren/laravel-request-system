<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Requests') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('release.version') }}">
</head>
<body>
@php($me = auth()->user())
<div class="shell" id="shell">
    <aside class="sidebar" aria-label="Main navigation">
        <a href="{{ route('requests.index') }}" class="brand">
            <span class="brand-mark"><x-icon name="logo" /></span>
            {{ config('app.name') }}
        </a>

        <nav class="nav">
            <span class="nav-label">Requests</span>
            <a href="{{ route('requests.index') }}" @class(['active' => request()->routeIs('requests.index', 'requests.show')])>
                <x-icon name="inbox" /> {{ $me->isAdmin() ? 'All requests' : 'My requests' }}
            </a>
            @can('create', App\Models\ServiceRequest::class)
                <a href="{{ route('requests.create') }}" @class(['active' => request()->routeIs('requests.create')])>
                    <x-icon name="plus" /> New request
                </a>
            @endcan

            <span class="nav-label">Account</span>
            <a href="{{ route('account.show') }}" @class(['active' => request()->routeIs('account.*')])>
                <x-icon name="user" /> My account
            </a>
            @if ($me->isAdmin())
                <a href="{{ route('accounts.index') }}" @class(['active' => request()->routeIs('accounts.*')])>
                    <x-icon name="users" /> Accounts
                </a>
            @endif
        </nav>

        <div class="sidebar-foot">
            v{{ config('release.version') }} · {{ \Illuminate\Support\Str::limit(config('release.revision'), 7, '') }} · {{ app()->environment() }}
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="crumbs">
                <button type="button" class="btn btn-ghost btn-sm menu-toggle" aria-label="Open menu"
                        onclick="document.getElementById('shell').classList.toggle('nav-open')">
                    <x-icon name="menu" />
                </button>
                <strong>@yield('crumb', 'Requests')</strong>
            </div>

            <div class="user-chip">
                <div class="user-meta">
                    <div class="name">{{ $me->name }}</div>
                    <div class="role">{{ $me->role }}</div>
                </div>
                <a href="{{ route('account.show') }}" title="My account"><x-avatar :name="$me->name" /></a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm" title="Sign out">
                        <x-icon name="logout" /><span class="sr-only">Sign out</span>
                    </button>
                </form>
            </div>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="alert alert-success" role="status"><x-icon name="check-circle" /><div>{{ session('success') }}</div></div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <x-icon name="alert" />
                    <div>
                        <strong>Please fix the following:</strong>
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="app-foot">
            <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
            <span>Version <span data-testid="app-version">{{ config('release.version') }}</span></span>
        </footer>
    </div>
</div>
<script>
    document.addEventListener('click', function (e) {
        var shell = document.getElementById('shell');
        if (shell.classList.contains('nav-open') && !e.target.closest('.sidebar') && !e.target.closest('.menu-toggle')) {
            shell.classList.remove('nav-open');
        }
    });
</script>
@stack('scripts')
</body>
</html>
