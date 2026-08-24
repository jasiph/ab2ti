<?php

namespace App\Policies;

use App\Models\MembershipDocument;
use App\Models\User;

class MembershipDocumentPolicy
{
    public function view(User $user, MembershipDocument $document): bool
    {
        return $user->can('membership-documents.view');
    }

    public function manage(User $user, MembershipDocument $document): bool
    {
        return $user->can('membership-documents.manage');
    }
}
