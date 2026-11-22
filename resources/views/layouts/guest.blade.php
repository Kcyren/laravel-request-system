<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('release.version') }}">
</head>
<body>
<div class="auth">
    <aside class="auth-aside">
        <a href="{{ route('login') }}" class="brand">
            <span class="brand-mark"><x-icon name="logo" /></span>
            {{ config('app.name') }}
        </a>

        <div class="auth-hero">
            <h1>Equipment and service requests, handled properly.</h1>
            <p>Submit what you need, track every decision, and keep a clean record. Built for students and lab administrators.</p>
            <ul class="auth-points">
                <li><span class="dot"><x-icon name="package" /></span><span>Submit requests in seconds and follow their status from pending to decision.</span></li>
                <li><span class="dot"><x-icon name="shield" /></span><span>You see only your own records. Only administrators can approve or reject.</span></li>
                <li><span class="dot"><x-icon name="clock" /></span><span>Every request keeps its requester, timestamps and current status.</span></li>
            </ul>
        </div>

        <p class="auth-foot">Version {{ config('release.version') }} · {{ ucfirst(app()->environment()) }}</p>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <a href="{{ route('login') }}" class="brand">
                <span class="brand-mark"><x-icon name="logo" /></span>
                {{ config('app.name') }}
            </a>
            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
