<?php

namespace App\Enums;

enum CooperativeImportComponentStatus: string
{
    case Ready = 'ready';
    case NeedsReview = 'needs_review';
    case Committed = 'committed';
    case None = 'none';
}
