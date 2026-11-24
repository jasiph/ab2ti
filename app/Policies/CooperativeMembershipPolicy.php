<?php

namespace App\Policies;

use App\Models\CooperativeMembership;
use App\Models\User;

class CooperativeMembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cooperative.view');
    }

    public function view(User $user, CooperativeMembership $cooperativeMembership): bool
    {
        return $user->can('cooperative.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cooperative.manage');
    }

    public function update(User $user, CooperativeMembership $cooperativeMembership): bool
    {
        return $user->can('cooperative.manage');
    }
}
