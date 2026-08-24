<?php

namespace App\Actions\Lapak;

use App\Enums\LapakListingAvailability;
use App\Exceptions\InvalidLapakTransition;
use App\Models\LapakListing;
use App\Models\User;
use App\Support\Lapak\LapakMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateLapakListing
{
    public function __construct(private readonly LapakMedia $media) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, mixed>  $fileIds
     * @param  array<int, mixed>  $removeMediaIds
     */
    public function handle(
        LapakListing $listing,
        array $payload,
        User $actor,
        ?UploadedFile $upload = null,
        array $fileIds = [],
        array $removeMediaIds = [],
    ): LapakListing {
        return DB::transaction(function () use ($listing, $payload, $actor, $upload, $fileIds, $removeMediaIds) {
            $listing = LapakListing::query()->lockForUpdate()->findOrFail($listing->id);

            if ($actor->ownsListing($listing) && ! $listing->moderation_status->ownerMayEditContent()) {
                throw new InvalidLapakTransition(
                    'Produk tidak dapat diubah pada status '.$listing->moderation_status->ownerLabel().'.',
                );
            }

            $listing->fill($payload);
            $listing->category_id = $payload['category_id'];
            $listing->availability = LapakListingAvailability::from($payload['availability']);
            $listing->updated_by = $actor->id;
            $listing->save();

            $this->media->syncListingImages($listing, $upload, $fileIds, $removeMediaIds, $actor->id);

            return $listing;
        });
    }
}
