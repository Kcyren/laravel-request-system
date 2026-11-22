@extends('layouts.app')

@section('title', 'New request')
@section('crumb', 'New request')

@section('content')
    <a href="{{ route('requests.index') }}" class="back"><x-icon name="arrow-left" /> Back to my requests</a>

    <div class="page-head">
        <div>
            <h1>New request</h1>
            <p>Tell the administrator what you need and why. New requests start as <strong>pending</strong>.</p>
        </div>
    </div>

    <div class="detail-grid">
        <div class="card">
            <div class="card-head">
                <div><h2>Request details</h2><p>All fields are required.</p></div>
            </div>
            <div class="card-body">
                @if ($formDisabled)
                    {{-- Lab 8 controlled-fault artifact only --}}
                    <div class="alert alert-warn" role="alert" data-testid="form-disabled">
                        <x-icon name="alert" />
                        <div>The request form is temporarily unavailable. Please try again later.</div>
                    </div>
                @else
                    <form method="POST" action="{{ route('requests.store') }}" data-testid="request-form" novalidate>
                        @csrf
                        <div class="form-row">
                            <div class="field">
                                <label class="label" for="item_name">Item or service</label>
                                <input id="item_name" type="text" name="item_name" maxlength="150" required
                                       @class(['input', 'is-invalid' => $errors->has('item_name')])
                                       value="{{ old('item_name') }}" placeholder="e.g. HDMI adapter">
                                @error('item_name')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label class="label" for="quantity">Quantity</label>
                                <input id="quantity" type="number" name="quantity" min="1" step="1" required
                                       @class(['input', 'is-invalid' => $errors->has('quantity')])
                                       value="{{ old('quantity', 1) }}">
                                @error('quantity')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="purpose">Purpose</label>
                            <textarea id="purpose" name="purpose" rows="5" maxlength="2000" required
                                      @class(['textarea', 'is-invalid' => $errors->has('purpose')])
                                      placeholder="What is it for, and when do you need it?">{{ old('purpose') }}</textarea>
                            <div class="hint">Up to 2,000 characters.</div>
                            @error('purpose')<div class="field-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-actions">
                            <a href="{{ route('requests.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary"><x-icon name="check" /> Submit request</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>What happens next</h2></div>
            <div class="card-body">
                <ul class="timeline">
                    <li><span class="tl-dot"></span><div><strong>Submitted</strong><div class="muted">Your request is saved as pending and linked to your account.</div></div></li>
                    <li><span class="tl-dot muted"></span><div><strong>Reviewed</strong><div class="muted">An administrator approves or rejects it.</div></div></li>
                    <li><span class="tl-dot muted"></span><div><strong>Decision</strong><div class="muted">The new status shows on your request list.</div></div></li>
                </ul>
            </div>
        </div>
    </div>
@endsection
