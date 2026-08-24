<?php

namespace App\Policies;

use App\Models\LapakListing;
use App\Models\User;

class LapakListingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('lapak.view');
    }

    public function view(User $user, LapakListing $listing): bool
    {
        return $user->can('lapak.view');
    }

    public function create(User $user): bool
    {
        return $user->can('lapak.manage');
    }

    public function update(User $user, LapakListing $listing): bool
    {
        return $user->can('lapak.manage');
    }

    public function moderate(User $user, LapakListing $listing): bool
    {
        return $user->can('lapak.moderate');
    }
}
