<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Access rules (Laboratory 3):
 *  - viewAny:      any signed-in user (the list itself is scoped by user_id)
 *  - view:         the owner (user_id) or an administrator; others get 403
 *  - create:       students only
 *  - updateStatus: administrator only
 */
class ServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->isAdmin() || $serviceRequest->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'student';
    }

    public function updateStatus(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->isAdmin();
    }
}
