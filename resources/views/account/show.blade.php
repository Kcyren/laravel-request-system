@extends('layouts.app')

@section('title', 'My account')
@section('crumb', 'My account')

@section('content')
    <div class="page-head">
        <div>
            <h1>My account</h1>
            <p>Your profile and sign-in security.</p>
        </div>
    </div>

    <div class="split">
        <div class="card">
            <div class="card-body">
                <div class="profile">
                    <x-avatar :name="$user->name" size="lg" />
                    <div>
                        <h2>{{ $user->name }}</h2>
                        <p class="muted">{{ $user->email }}</p>
                        <p style="margin-top:8px;"><span @class(['badge', 'badge-role', 'badge-plain', 'admin' => $user->isAdmin()])>{{ $user->role }}</span></p>
                    </div>
                </div>
            </div>
            <div class="card-body" style="border-top:1px solid var(--line-soft);">
                <dl class="dl">
                    <dt>Role</dt>
                    <dd style="text-transform:capitalize;">{{ $user->role }}</dd>
                    <dt>Member since</dt>
                    <dd>{{ $user->created_at?->format('F j, Y') ?? '—' }}</dd>
                    <dt>{{ $user->isAdmin() ? 'Requests (all)' : 'My requests' }}</dt>
                    <dd>{{ $user->isAdmin() ? \App\Models\ServiceRequest::count() : $requestCount }}</dd>
                </dl>
                <p class="hint" style="margin-top:14px;">
                    <x-icon name="info" style="width:14px;height:14px;vertical-align:-2px;" />
                    Name, email and role are managed by the lab administrator.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div><h2>Change password</h2><p>Use at least 8 characters with letters and numbers.</p></div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('account.password') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label class="label" for="current_password">Current password</label>
                        <div class="input-group has-toggle">
                            <x-icon name="lock" class="icon icon-left" />
                            <input id="current_password" type="password" name="current_password" required autocomplete="current-password"
                                   @class(['input', 'is-invalid' => $errors->password->has('current_password')])>
                            <button type="button" class="toggle" data-toggle-password="current_password" aria-label="Show password"><x-icon name="eye" /></button>
                        </div>
                        @if ($errors->password->has('current_password'))<div class="field-error">{{ $errors->password->first('current_password') }}</div>@endif
                    </div>

                    <div class="field">
                        <label class="label" for="password">New password</label>
                        <div class="input-group has-toggle">
                            <x-icon name="key" class="icon icon-left" />
                            <input id="password" type="password" name="password" required autocomplete="new-password"
                                   @class(['input', 'is-invalid' => $errors->password->has('password')])>
                            <button type="button" class="toggle" data-toggle-password="password" aria-label="Show password"><x-icon name="eye" /></button>
                        </div>
                        @if ($errors->password->has('password'))<div class="field-error">{{ $errors->password->first('password') }}</div>@endif
                    </div>

                    <div class="field">
                        <label class="label" for="password_confirmation">Confirm new password</label>
                        <div class="input-group">
                            <x-icon name="key" class="icon icon-left" />
                            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><x-icon name="check" /> Update password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.password-toggle')
@endpush
