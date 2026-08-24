<?php

namespace App\Policies;

use App\Models\LapakCategory;
use App\Models\User;

class LapakCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('lapak.view');
    }

    public function view(User $user, LapakCategory $category): bool
    {
        return $user->can('lapak.view');
    }

    public function create(User $user): bool
    {
        return $user->can('lapak.manage');
    }

    public function update(User $user, LapakCategory $category): bool
    {
        return $user->can('lapak.manage');
    }
}
