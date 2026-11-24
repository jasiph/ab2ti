<?php

namespace App\Enums;

enum CooperativeImportRowStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case NeedsReview = 'needs_review';
    case Invalid = 'invalid';
    case Committed = 'committed';
}
