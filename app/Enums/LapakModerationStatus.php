<?php

namespace App\Enums;

enum LapakModerationStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::PendingReview => 'Menunggu tinjauan',
            self::Published => 'Tayang',
            self::Rejected => 'Ditolak',
            self::Suspended => 'Ditangguhkan',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTargets(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview, self::Published],
            self::PendingReview => [self::Published, self::Rejected, self::Draft],
            self::Published => [self::Suspended, self::Draft, self::PendingReview],
            self::Rejected => [self::Draft, self::PendingReview],
            self::Suspended => [self::Published, self::Draft],
        };
    }

    public function isPublicEligible(): bool
    {
        return $this === self::Published;
    }

    public function ownerLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::PendingReview => 'Menunggu Tinjauan',
            self::Published => 'Tayang',
            self::Rejected => 'Perlu Diperbaiki',
            self::Suspended => 'Ditangguhkan',
        };
    }

    public function ownerMayEditContent(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Published], true);
    }

    public function ownerMaySubmitForReview(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function ownerMayChangeAvailability(): bool
    {
        return $this !== self::Suspended;
    }
}
