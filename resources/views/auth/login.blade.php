@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div class="card" style="max-width:420px; margin:2rem auto;">
        <h2>Sign in</h2>
        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <div class="row">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div class="row">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit">Sign In</button>
        </form>
    </div>
@endsection
