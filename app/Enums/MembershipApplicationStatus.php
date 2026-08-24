<?php

namespace App\Enums;

enum MembershipApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case RevisionRequested = 'revision_requested';
    case Rejected = 'rejected';
    case Approved = 'approved';

    public function isTerminal(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderReview],
            self::UnderReview => [self::RevisionRequested, self::Rejected, self::Approved],
            self::RevisionRequested => [self::Submitted],
            self::Approved, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
