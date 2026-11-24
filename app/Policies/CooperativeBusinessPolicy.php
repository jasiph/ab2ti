<?php

namespace App\Policies;

use App\Models\CooperativeBusiness;
use App\Models\User;

class CooperativeBusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cooperative.view');
    }

    public function view(User $user, CooperativeBusiness $cooperativeBusiness): bool
    {
        return $user->can('cooperative.view');
    }
}
