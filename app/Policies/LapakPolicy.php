<?php

namespace App\Policies;

use App\Models\Lapak;
use App\Models\User;

class LapakPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('lapak.view');
    }

    public function view(User $user, Lapak $lapak): bool
    {
        return $user->can('lapak.view');
    }

    public function create(User $user): bool
    {
        return $user->can('lapak.manage');
    }

    public function update(User $user, Lapak $lapak): bool
    {
        return $user->can('lapak.manage');
    }

    public function moderate(User $user, Lapak $lapak): bool
    {
        return $user->can('lapak.moderate');
    }
}
