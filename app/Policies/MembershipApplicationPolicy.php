<?php

namespace App\Policies;

use App\Models\MembershipApplication;
use App\Models\User;

class MembershipApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('membership-applications.view');
    }

    public function view(User $user, MembershipApplication $application): bool
    {
        return $user->can('membership-applications.view');
    }

    public function review(User $user, MembershipApplication $application): bool
    {
        return $user->can('membership-applications.review');
    }

    public function approve(User $user, MembershipApplication $application): bool
    {
        return $user->can('membership-applications.approve');
    }

    public function reject(User $user, MembershipApplication $application): bool
    {
        return $user->can('membership-applications.reject');
    }

    public function requestRevision(User $user, MembershipApplication $application): bool
    {
        return $user->can('membership-applications.request-revision');
    }
}
