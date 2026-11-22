@extends('layouts.app')

@section('title', 'Accounts')
@section('crumb', 'Accounts')

@section('content')
    <div class="page-head">
        <div>
            <h1>Accounts</h1>
            <p>Everyone who can sign in to the request system.</p>
        </div>
    </div>

    <div class="alert alert-info">
        <x-icon name="shield" />
        <div>Roles are assigned only through trusted setup (the database seeder), never from this screen. This keeps anyone from promoting an account to administrator through the web interface.</div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th class="num">Requests</th>
                        <th class="hide-sm">Member since</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr>
                            <td>
                                <div class="person">
                                    <x-avatar :name="$account->name" />
                                    <div>
                                        <div class="item">{{ $account->name }}@if ($account->is(auth()->user())) <span class="muted small">(you)</span>@endif</div>
                                        <div class="sub">{{ $account->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span @class(['badge', 'badge-role', 'badge-plain', 'admin' => $account->role === 'admin'])>{{ $account->role }}</span></td>
                            <td class="num">{{ $account->service_requests_count }}</td>
                            <td class="hide-sm sub">{{ $account->created_at?->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $accounts->links('partials.pagination') }}
    </div>
@endsection
