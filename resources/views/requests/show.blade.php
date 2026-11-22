@extends('layouts.app')

@section('title', 'Request #'.$serviceRequest->id)

@section('content')
    <p><a href="{{ route('requests.index') }}">&larr; Back to Requests</a></p>

    <div class="card">
        <h2>Request #<span data-testid="request-id">{{ $serviceRequest->id }}</span></h2>
        {{-- All user-provided text uses escaped {{ }} output (Lab 3). --}}
        <p><strong>Requester:</strong> {{ $serviceRequest->requester_name }} ({{ $serviceRequest->requester_email }})</p>
        <p><strong>Item Name:</strong> {{ $serviceRequest->item_name }}</p>
        <p><strong>Quantity:</strong> {{ $serviceRequest->quantity }}</p>
        <p><strong>Status:</strong> <span class="badge {{ $serviceRequest->status }}" data-testid="request-status">{{ $serviceRequest->status }}</span></p>
        <p><strong>Purpose:</strong><br>{{ $serviceRequest->purpose }}</p>
        <p><small>Created {{ $serviceRequest->created_at }} &middot; Updated {{ $serviceRequest->updated_at }}</small></p>
    </div>

    @can('updateStatus', $serviceRequest)
        <div class="card">
            <h3>Update Status (Administrator only)</h3>
            <form method="POST" action="{{ route('requests.updateStatus', $serviceRequest) }}">
                @csrf
                @method('PATCH')
                <div class="row">
                    <select name="status" aria-label="Status">
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($serviceRequest->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit">Save Status</button>
            </form>
        </div>
    @endcan
@endsection
