@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h2>Welcome back</h2>
    <p class="lead">Sign in to manage your requests.</p>

    @if (session('status'))
        <div class="alert alert-info" role="status"><x-icon name="info" /><div>{{ session('status') }}</div></div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" role="alert"><x-icon name="alert" /><div>{{ $errors->first() }}</div></div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}" novalidate>
        @csrf

        <div class="field">
            <label class="label" for="email">Email address</label>
            <div class="input-group">
                <x-icon name="mail" class="icon icon-left" />
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       @class(['input', 'is-invalid' => $errors->has('email')])
                       placeholder="you@example.edu" required autofocus autocomplete="username">
            </div>
        </div>

        <div class="field">
            <label class="label" for="password">Password</label>
            <div class="input-group has-toggle">
                <x-icon name="lock" class="icon icon-left" />
                <input id="password" type="password" name="password" class="input"
                       placeholder="Enter your password" required autocomplete="current-password">
                <button type="button" class="toggle" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                    <x-icon name="eye" />
                </button>
            </div>
        </div>

        <div class="auth-row">
            <label class="check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in</label>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Sign in <x-icon name="arrow-right" /></button>
    </form>

    <div class="auth-note">
        <x-icon name="shield" />
        <span>Accounts are issued by the lab administrator. Contact your administrator if you cannot sign in.</span>
    </div>
@endsection

@push('scripts')
    @include('partials.password-toggle')
@endpush
