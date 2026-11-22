@extends('layouts.app')

@php($isAdmin = auth()->user()->isAdmin())

@section('title', $isAdmin ? 'All Requests' : 'My Requests')
@section('crumb', $isAdmin ? 'All requests' : 'My requests')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $isAdmin ? 'All Requests' : 'My Requests' }}</h1>
            <p>{{ $isAdmin ? 'Review and decide on requests from every student.' : 'Track the equipment and services you have requested.' }}</p>
        </div>
        @can('create', App\Models\ServiceRequest::class)
            <a href="{{ route('requests.create') }}" class="btn btn-primary"><x-icon name="plus" /> New request</a>
        @endcan
    </div>

    @if ($formDisabled)
        {{-- Lab 8 controlled-fault artifact only --}}
        <div class="alert alert-warn" role="alert" data-testid="form-disabled">
            <x-icon name="alert" />
            <div>The request form is temporarily unavailable. Please try again later.</div>
        </div>
    @endif

    <section class="stats" aria-label="Summary">
        <div class="card stat">
            <span class="stat-icon total"><x-icon name="list" /></span>
            <div><div class="stat-value">{{ $stats['total'] }}</div><div class="stat-label">Total requests</div></div>
        </div>
        <div class="card stat">
            <span class="stat-icon pending"><x-icon name="clock" /></span>
            <div><div class="stat-value">{{ $stats['pending'] }}</div><div class="stat-label">Pending</div></div>
        </div>
        <div class="card stat">
            <span class="stat-icon approved"><x-icon name="check-circle" /></span>
            <div><div class="stat-value">{{ $stats['approved'] }}</div><div class="stat-label">Approved</div></div>
        </div>
        <div class="card stat">
            <span class="stat-icon rejected"><x-icon name="x-circle" /></span>
            <div><div class="stat-value">{{ $stats['rejected'] }}</div><div class="stat-label">Rejected</div></div>
        </div>
    </section>

    <div class="card">
        <form method="GET" action="{{ route('requests.index') }}" class="toolbar" role="search">
            <div class="input-group">
                <x-icon name="search" class="icon icon-left" />
                <label for="search" class="sr-only">Search by item</label>
                <input id="search" type="search" name="search" maxlength="150" class="input"
                       placeholder="Search by item name…" value="{{ $filters['search'] ?? '' }}">
            </div>
            <label for="status-filter" class="sr-only">Status</label>
            <select id="status-filter" name="status" class="select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary">Apply</button>
            @if (! empty($filters['search']) || ! empty($filters['status']))
                <a href="{{ route('requests.index') }}" class="btn btn-ghost">Clear</a>
            @endif
        </form>

        @if ($requests->isEmpty())
            <div class="empty">
                <div class="empty-icon"><x-icon name="inbox" /></div>
                @if (! empty($filters['search']) || ! empty($filters['status']))
                    <h3>No matching requests</h3>
                    <p>Try a different search term or status.</p>
                    <a href="{{ route('requests.index') }}" class="btn btn-secondary">Clear filters</a>
                @else
                    <h3>No requests yet</h3>
                    <p>{{ $isAdmin ? 'Requests submitted by students will appear here.' : 'When you submit a request it will appear here.' }}</p>
                    @can('create', App\Models\ServiceRequest::class)
                        <a href="{{ route('requests.create') }}" class="btn btn-primary"><x-icon name="plus" /> Create your first request</a>
                    @endcan
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item</th>
                            @if ($isAdmin)<th class="hide-sm">Requester</th>@endif
                            <th class="num">Qty</th>
                            <th>Status</th>
                            <th class="hide-sm hide-md">Submitted</th>
                            <th class="actions"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $item)
                            <tr>
                                <td class="id">#{{ $item->id }}</td>
                                <td class="cell-item">
                                    <div class="item"><a href="{{ route('requests.show', $item) }}">{{ $item->item_name }}</a></div>
                                    <div class="sub">{{ \Illuminate\Support\Str::limit($item->purpose, 60) }}</div>
                                </td>
                                @if ($isAdmin)
                                    <td class="hide-sm">
                                        <div class="person">
                                            <x-avatar :name="$item->requester_name" />
                                            <div>
                                                <div>{{ $item->requester_name }}</div>
                                                <div class="sub">{{ $item->requester_email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                                <td class="num">{{ $item->quantity }}</td>
                                <td><x-status-badge :status="$item->status" /></td>
                                <td class="hide-sm hide-md sub cell-date">{{ $item->created_at?->format('M j, Y') }}</td>
                                <td class="actions">
                                    <a href="{{ route('requests.show', $item) }}" class="btn btn-secondary btn-sm">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $requests->links('partials.pagination') }}
        @endif
    </div>
@endsection
