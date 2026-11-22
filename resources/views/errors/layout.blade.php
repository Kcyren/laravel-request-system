<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('heading')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    {{-- Safe error page: no stack traces, SQL or configuration details. --}}
    <div class="error-page">
        <div class="error-card">
            <div class="error-code">@yield('code')</div>
            <h1>@yield('heading')</h1>
            <p>@yield('message')</p>
            <a href="{{ url('/') }}" class="btn btn-primary">Back to requests</a>
        </div>
    </div>
</body>
</html>
