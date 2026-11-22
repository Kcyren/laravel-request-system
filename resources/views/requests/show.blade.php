@extends('layouts.app')

@section('title', 'Request #'.$serviceRequest->id)
@section('crumb', 'Request #'.$serviceRequest->id)

@section('content')
    <a href="{{ route('requests.index') }}" class="back"><x-icon name="arrow-left" /> Back to requests</a>

    <div class="page-head">
        <div>
            <div class="title-row">
                <h1>Request #<span data-testid="request-id">{{ $serviceRequest->id }}</span></h1>
                <x-status-badge :status="$serviceRequest->status" testid="request-status" />
            </div>
            <p>Submitted {{ $serviceRequest->created_at?->format('F j, Y \a\t g:i A') }}</p>
        </div>
    </div>

    <div class="detail-grid">
        <div class="card">
            <div class="card-head"><h2>Details</h2></div>
            <div class="card-body">
                {{-- All user-provided text uses escaped {{ }} output (Lab 3). --}}
                <dl class="dl">
                    <dt>Item</dt>
                    <dd><strong>{{ $serviceRequest->item_name }}</strong></dd>
                    <dt>Quantity</dt>
                    <dd>{{ $serviceRequest->quantity }}</dd>
                    <dt>Requester</dt>
                    <dd>
                        <div class="person">
                            <x-avatar :name="$serviceRequest->requester_name" />
                            <div>
                                <div>{{ $serviceRequest->requester_name }}</div>
                                <div class="sub muted small">{{ $serviceRequest->requester_email }}</div>
                            </div>
                        </div>
                    </dd>
                    <dt>Purpose</dt>
                    <dd class="prose">{{ $serviceRequest->purpose }}</dd>
                </dl>
            </div>
        </div>

        <div class="stack">
            @can('updateStatus', $serviceRequest)
                <div class="card">
                    <div class="card-head"><div><h2>Decision</h2><p>Administrator only</p></div></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('requests.updateStatus', $serviceRequest) }}">
                            @csrf
                            @method('PATCH')
                            <fieldset class="status-options" style="border:0; padding:0; margin:0 0 16px;">
                                <legend class="sr-only">Status</legend>
                                @foreach ($statuses as $status)
                                    <label class="status-option">
                                        <input type="radio" name="status" value="{{ $status }}" @checked($serviceRequest->status === $status)>
                                        <x-status-badge :status="$status" />
                                    </label>
                                @endforeach
                            </fieldset>
                            <button type="submit" class="btn btn-primary btn-block"><x-icon name="check" /> Save decision</button>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card">
                <div class="card-head"><h2>Activity</h2></div>
                <div class="card-body">
                    <ul class="timeline">
                        <li>
                            <span class="tl-dot"></span>
                            <div><strong>Submitted</strong><div class="muted small">{{ $serviceRequest->created_at?->format('M j, Y g:i A') }}</div></div>
                        </li>
                        <li>
                            <span @class(['tl-dot', 'muted' => $serviceRequest->status === 'pending'])></span>
                            <div>
                                <strong>{{ $serviceRequest->status === 'pending' ? 'Awaiting decision' : 'Marked '.$serviceRequest->status }}</strong>
                                <div class="muted small">Last updated {{ $serviceRequest->updated_at?->format('M j, Y g:i A') }}</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
