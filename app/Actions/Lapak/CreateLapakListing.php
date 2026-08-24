<?php

namespace App\Actions\Lapak;

use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Models\Lapak;
use App\Models\LapakListing;
use App\Models\User;
use App\Support\Lapak\LapakMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateLapakListing
{
    public function __construct(private readonly LapakMedia $media) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, mixed>  $fileIds
     */
    public function handle(
        array $payload,
        User $actor,
        ?UploadedFile $upload = null,
        array $fileIds = [],
    ): LapakListing {
        return DB::transaction(function () use ($payload, $actor, $upload, $fileIds) {
            $lapak = Lapak::query()->lockForUpdate()->findOrFail($payload['lapak_id']);

            $listing = new LapakListing;
            $listing->fill($payload);
            $listing->lapak_id = $lapak->id;
            $listing->category_id = $payload['category_id'];
            $listing->availability = LapakListingAvailability::from($payload['availability']);
            $listing->moderation_status = LapakModerationStatus::Draft;
            $listing->created_by = $actor->id;
            $listing->updated_by = $actor->id;
            $listing->save();

            $this->media->syncListingImages($listing, $upload, $fileIds, [], $actor->id);

            return $listing;
        });
    }
}
