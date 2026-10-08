<!DOCTYPE html>
<html>
<head><title>Request Details</title></head>
<body style="font-family:sans-serif; margin:20px;">
    <p><a href="{{ route('requests.index') }}">&larr; Back to Requests</a></p>

    @if (session('success'))
        <div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; border: 1px solid #c3e6cb; margin-bottom: 15px;">
            {{ session('success') }}
        </div>
    @endif

    <h2>Request #{{ $serviceRequest->id }}</h2>
    <p><strong>Item:</strong> {{ $serviceRequest->item_name }}</p>
    <p><strong>Quantity:</strong> {{ $serviceRequest->quantity }}</p>
    <p><strong>Purpose:</strong> {{ $serviceRequest->purpose }}</p>
    <p><strong>Status:</strong> {{ $serviceRequest->status }}</p>
    <p><strong>Owner ID:</strong> {{ $serviceRequest->user_id }}</p>

    @can('updateStatus', $serviceRequest)
        <hr>
        <h3>Update Status (Admin Only)</h3>
        <form method="POST" action="{{ route('requests.updateStatus', $serviceRequest) }}">
            @csrf
            @method('PATCH')
            <select name="status">
                <option value="pending" {{ $serviceRequest->status === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ $serviceRequest->status === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ $serviceRequest->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
            <button type="submit">Update Status</button>
        </form>
    @endcan
</body>
</html>
