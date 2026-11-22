@extends('layouts.app')

@section('title', 'Requests')

@section('content')
    @can('create', App\Models\ServiceRequest::class)
        <div class="card">
            <h3>New Service Request</h3>
            @if ($formDisabled)
                {{-- Lab 8 controlled-fault artifact only --}}
                <p class="flash-err" data-testid="form-disabled">The request form is temporarily unavailable.</p>
            @else
                <form method="POST" action="{{ route('requests.store') }}" data-testid="request-form">
                    @csrf
                    <div class="row">
                        <label for="item_name">Item Name</label>
                        <input id="item_name" type="text" name="item_name" maxlength="150" value="{{ old('item_name') }}" required>
                    </div>
                    <div class="row">
                        <label for="quantity">Quantity</label>
                        <input id="quantity" type="number" name="quantity" min="1" step="1" value="{{ old('quantity', 1) }}" required>
                    </div>
                    <div class="row">
                        <label for="purpose">Purpose</label>
                        <textarea id="purpose" name="purpose" rows="3" maxlength="2000" required>{{ old('purpose') }}</textarea>
                    </div>
                    <button type="submit">Submit Request</button>
                </form>
            @endif
        </div>
    @endcan

    <div class="card">
        <h3>{{ auth()->user()->isAdmin() ? 'All Requests' : 'My Requests' }}</h3>

        <form method="GET" action="{{ route('requests.index') }}" class="filters row">
            <div>
                <label for="search">Search item</label>
                <input id="search" type="text" name="search" maxlength="150" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div>
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">Any</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:0 0 auto;"><button type="submit">Filter</button></div>
        </form>

        <table>
            <thead>
                <tr><th>ID</th><th>Requester</th><th>Item</th><th>Qty</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($requests as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>{{ $item->requester_name }}</td>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td><span class="badge {{ $item->status }}">{{ $item->status }}</span></td>
                        <td><a href="{{ route('requests.show', $item) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">No requests found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:1rem;">{{ $requests->links('partials.pagination') }}</div>
    </div>
@endsection
