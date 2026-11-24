<?php

namespace App\Policies;

use App\Models\CooperativeImportBatch;
use App\Models\User;

class CooperativeImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cooperative.view');
    }

    public function view(User $user, CooperativeImportBatch $cooperativeImportBatch): bool
    {
        return $user->can('cooperative.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cooperative.manage');
    }

    public function update(User $user, CooperativeImportBatch $cooperativeImportBatch): bool
    {
        return $user->can('cooperative.manage');
    }
}
