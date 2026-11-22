<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Service Requests') - {{ config('app.name') }}</title>
    <style>
        :root { --fg:#1f2933; --muted:#616e7c; --line:#d9e2ec; --bg:#f5f7fa; --accent:#2f6fde; --ok:#1e7e34; --err:#c0392b; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color:var(--fg); background:var(--bg); }
        header { background:#fff; border-bottom:1px solid var(--line); }
        .wrap { max-width:900px; margin:0 auto; padding:1rem; }
        header .wrap { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; }
        .card { background:#fff; border:1px solid var(--line); border-radius:8px; padding:1rem 1.25rem; margin-bottom:1rem; }
        label { display:block; font-weight:600; margin-bottom:.25rem; }
        input, textarea, select { width:100%; padding:.5rem; border:1px solid var(--line); border-radius:6px; font:inherit; }
        .row { margin-bottom:.75rem; }
        button, .btn { background:var(--accent); color:#fff; border:0; border-radius:6px; padding:.5rem 1rem; font:inherit; cursor:pointer; text-decoration:none; display:inline-block; }
        button.secondary { background:#52606d; }
        table { width:100%; border-collapse:collapse; background:#fff; }
        th, td { text-align:left; padding:.5rem; border-bottom:1px solid var(--line); vertical-align:top; }
        .flash-ok { color:var(--ok); } .flash-err { color:var(--err); }
        .badge { padding:.1rem .5rem; border-radius:999px; font-size:.85rem; background:#e4e7eb; }
        .badge.approved { background:#d4edda; } .badge.rejected { background:#f8d7da; } .badge.pending { background:#fff3cd; }
        .filters { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; }
        .filters > div { flex:1 1 180px; }
        footer { color:var(--muted); font-size:.8rem; text-align:center; padding:1rem; }
        nav[role=navigation] svg { width:1rem; height:1rem; }
    </style>
</head>
<body>
<header>
    <div class="wrap">
        <strong>{{ config('app.name') }} &mdash; Service Request System</strong>
        @auth
            <div style="display:flex; gap:.75rem; align-items:center;">
                <span>{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="secondary">Logout</button>
                </form>
            </div>
        @endauth
    </div>
</header>

<main class="wrap">
    @if (session('success'))
        <p class="flash-ok" role="status">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <ul class="flash-err" role="alert">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    @yield('content')
</main>

<footer>
    Version <span data-testid="app-version">{{ config('release.version') }}</span>
    &middot; revision {{ \Illuminate\Support\Str::limit(config('release.revision'), 12, '') }}
    &middot; {{ app()->environment() }}
</footer>
</body>
</html>
