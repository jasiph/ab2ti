<?php

namespace App\Enums;

enum CooperativeImportReviewStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case AcceptedAsIs = 'accepted_as_is';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
