<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ServiceRequest::class);
        $user = $request->user();

        $requests = ($user->role === 'admin')
            ? ServiceRequest::latest()->paginate(10)
            : ServiceRequest::where('user_id', $user->id)->latest()->paginate(10);

        return view('requests.index', compact('requests'));
    }

    public function show(ServiceRequest $serviceRequest)
    {
        Gate::authorize('view', $serviceRequest);
        return view('requests.show', compact('serviceRequest'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', ServiceRequest::class);

        $validated = $request->validate([
            'item_name' => 'required|string|max:150',
            'quantity'  => 'required|integer|min:1',
            'purpose'   => 'required|string|max:2000',
        ]);

        $user = $request->user();

        ServiceRequest::create([
            'user_id'         => $user->id,
            'requester_name'  => $user->name,
            'requester_email' => $user->email,
            'item_name'       => $validated['item_name'],
            'quantity'        => $validated['quantity'],
            'purpose'         => $validated['purpose'],
            'status'          => 'pending',
        ]);

        return redirect()->route('requests.index')->with('success', 'Request created successfully.');
    }

    public function updateStatus(Request $request, ServiceRequest $serviceRequest)
    {
        Gate::authorize('updateStatus', $serviceRequest);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $serviceRequest->update([
            'status' => $validated['status'],
        ]);

        return redirect()->route('requests.show', $serviceRequest)->with('success', 'Status updated successfully.');
    }
}
