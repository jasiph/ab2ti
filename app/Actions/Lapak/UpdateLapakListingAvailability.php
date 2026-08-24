<?php

namespace App\Actions\Lapak;

use App\Enums\LapakListingAvailability;
use App\Exceptions\InvalidLapakTransition;
use App\Models\LapakListing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateLapakListingAvailability
{
    public function handle(
        LapakListing $listing,
        LapakListingAvailability $availability,
        User $actor,
    ): LapakListing {
        return DB::transaction(function () use ($listing, $availability, $actor) {
            $listing = LapakListing::query()->lockForUpdate()->findOrFail($listing->id);

            if ($actor->ownsListing($listing) && ! $listing->moderation_status->ownerMayChangeAvailability()) {
                throw new InvalidLapakTransition(
                    'Ketersediaan tidak dapat diubah pada status '.$listing->moderation_status->ownerLabel().'.',
                );
            }

            $listing->availability = $availability;
            $listing->updated_by = $actor->id;
            $listing->save();

            return $listing;
        });
    }
}
