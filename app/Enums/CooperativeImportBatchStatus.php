<?php

namespace App\Enums;

enum CooperativeImportBatchStatus: string
{
    case Uploaded = 'uploaded';
    case Parsed = 'parsed';
    case ReviewRequired = 'review_required';
    case Ready = 'ready';
    case Committed = 'committed';
    case CompletedWithReview = 'completed_with_review';
    case Replaced = 'replaced';
    case Failed = 'failed';
    case Reversed = 'reversed';

    public function isTerminalSuccess(): bool
    {
        return $this === self::Committed || $this === self::CompletedWithReview;
    }
}
