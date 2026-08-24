<?php

namespace App\Actions\Lapak;

use App\Enums\LapakModerationStatus;
use App\Exceptions\InvalidLapakTransition;
use App\Models\LapakListing;
use App\Models\User;

class SubmitLapakListingForReview
{
    public function __construct(private readonly TransitionLapakListingModeration $transition) {}

    public function handle(LapakListing $listing, User $actor): LapakListing
    {
        if (! $actor->ownsListing($listing)) {
            throw new InvalidLapakTransition('Anda hanya dapat mengirim produk milik sendiri.');
        }

        if (! $listing->moderation_status->ownerMaySubmitForReview()
            && $listing->moderation_status !== LapakModerationStatus::Published) {
            throw new InvalidLapakTransition(
                'Produk tidak dapat dikirim untuk ditinjau pada status '.$listing->moderation_status->ownerLabel().'.',
            );
        }

        return $this->transition->handle($listing, LapakModerationStatus::PendingReview, $actor);
    }
}
