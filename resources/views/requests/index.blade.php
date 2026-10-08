<!DOCTYPE html>
<html>
<head><title>Request System</title></head>
<body style="font-family:sans-serif; margin:20px;">
    <h2>Service Requests</h2>
    <p>User: <strong>{{ auth()->user()->name }}</strong> | Role: <strong>{{ auth()->user()->role }}</strong></p>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Log Out</button>
    </form>
    <hr>
    <h3>Create Request</h3>
    @if ($errors->any())
        <div style="color:red;">
            <ul>@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ route('requests.store') }}">
        @csrf
        <p>Item: <input type="text" name="item_name" value="{{ old('item_name') }}"></p>
        <p>Quantity: <input type="number" name="quantity" value="{{ old('quantity') }}"></p>
        <p>Purpose: <textarea name="purpose">{{ old('purpose') }}</textarea></p>
        <button type="submit">Submit Request</button>
    </form>
    <hr>
    <h3>Accessible Records</h3>
    <ul>
    @foreach ($requests as $req)
        <li>
            #{{ $req->id }}: <a href="{{ route('requests.show', $req) }}">{{ $req->item_name }}</a>
            (Qty: {{ $req->quantity }}) - Status: <strong>{{ $req->status }}</strong> (Owner: {{ $req->user_id }})
        </li>
    @endforeach
    </ul>
</body>
</html>
