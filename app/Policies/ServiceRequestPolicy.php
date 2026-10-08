<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->id === $serviceRequest->user_id || $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function updateStatus(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->role === 'admin';
    }
}
