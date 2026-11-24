<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ServiceRequestController extends Controller
{
    /** Allowed request states (Laboratory 2 status field, string(20)). */
    public const STATUSES = ['pending', 'approved', 'rejected'];

    /**
     * GET /requests
     * Students see only their own records (scoped by user_id); the
     * administrator sees all. The scope applies to search and pagination too.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ServiceRequest::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
        ]);

        $user = $request->user();

        // Same ownership scope for the summary cards, search, filters and pagination.
        $scoped = fn () => ServiceRequest::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id));

        $stats = $scoped()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $requests = $scoped()
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('item_name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'stats' => [
                'total' => (int) $stats->sum(),
                'pending' => (int) ($stats['pending'] ?? 0),
                'approved' => (int) ($stats['approved'] ?? 0),
                'rejected' => (int) ($stats['rejected'] ?? 0),
            ],
            'formDisabled' => config('release.lab_fault'),
        ]);
    }

    /**
     * GET /requests/create - students only.
     */
    public function create()
    {
        Gate::authorize('create', ServiceRequest::class);

        return view('requests.create', [
            'formDisabled' => config('release.lab_fault'),
        ]);
    }

    /**
     * GET /requests/{id}
     * Owner or administrator only. Another student's record returns 403.
     */
    public function show(ServiceRequest $serviceRequest)
    {
        Gate::authorize('view', $serviceRequest);

        return view('requests.show', [
            'serviceRequest' => $serviceRequest,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * POST /requests
     * Students only. Ownership, requester details and status are assigned
     * on the server; client-supplied user_id/status/is_admin/role are rejected.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', ServiceRequest::class);

        // Laboratory 8 controlled-fault artifact: the request form is disabled.
        abort_if(config('release.lab_fault'), 503, 'Request form is temporarily unavailable.');

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1'],
            'purpose' => ['required', 'string', 'max:2000'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'is_admin' => ['prohibited'],
            'role' => ['prohibited'],
        ]);

        $user = $request->user();

        $serviceRequest = ServiceRequest::create([
            'user_id' => $user->id,
            'requester_name' => $user->name,
            'requester_email' => $user->email,
            'item_name' => $validated['item_name'],
            'quantity' => (int) $validated['quantity'],
            'purpose' => $validated['purpose'],
            'status' => 'pending',
        ]);

        // Lab 10: structured log, written only after the record was saved.
        // No passwords, tokens, request descriptions or personal information.
        Log::info('request.created', [
            'request_id' => $request->attributes->get('request_id'),
            'record_id' => $serviceRequest->id,
            'outcome' => 'success',
        ]);

        return redirect()
            ->route('requests.show', $serviceRequest)
            ->with('success', "Request #{$serviceRequest->id} submitted.");
    }

    /**
     * PATCH /requests/{id}/status
     * Administrator only. Authorized before any write; only status changes.
     */
    public function updateStatus(Request $request, ServiceRequest $serviceRequest)
    {
        Gate::authorize('updateStatus', $serviceRequest);

        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
        ]);

        $serviceRequest->update(['status' => $validated['status']]);

        // Lab 10: structured log, written only after the update succeeded.
        Log::info('request.status_updated', [
            'request_id' => $request->attributes->get('request_id'),
            'record_id' => $serviceRequest->id,
            'status' => $validated['status'],
            'outcome' => 'success',
        ]);

        return redirect()
            ->route('requests.show', $serviceRequest)
            ->with('success', 'Status updated.');
    }
}
