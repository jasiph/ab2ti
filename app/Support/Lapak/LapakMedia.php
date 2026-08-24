<?php

namespace App\Support\Lapak;

use App\Models\File;
use App\Models\Lapak;
use App\Models\LapakListing;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class LapakMedia
{
    public function syncCover(
        Lapak $lapak,
        ?UploadedFile $upload,
        ?int $existingFileId,
        bool $remove,
        int $userId,
    ): void {
        $this->syncSingle($lapak, 'lapak-cover', $upload, $existingFileId, $remove, $userId);
    }

    /**
     * @param  array<int, mixed>  $fileIds
     * @param  array<int, mixed>  $removeMediaIds
     */
    public function syncListingImages(
        LapakListing $listing,
        ?UploadedFile $upload,
        array $fileIds,
        array $removeMediaIds,
        int $userId,
    ): void {
        if ($upload instanceof UploadedFile) {
            $asset = File::query()->create([
                'name' => pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME),
                'user_id' => $userId,
            ]);
            $media = $asset->addMedia($upload)->toMediaCollection('default');
            $media->copy($listing, 'listing-images');
        }

        foreach ($fileIds as $fileId) {
            $asset = File::query()->with('media')->find($fileId);
            $media = $asset?->getFirstMedia('default');

            if ($media) {
                $media->copy($listing, 'listing-images');
            }
        }

        if ($removeMediaIds !== []) {
            $listing->media()
                ->whereIn('id', $removeMediaIds)
                ->get()
                ->each(fn (Media $media) => $media->delete());
        }
    }

    private function syncSingle(
        HasMedia $model,
        string $collection,
        ?UploadedFile $upload,
        ?int $existingFileId,
        bool $remove,
        int $userId,
    ): void {
        if ($upload instanceof UploadedFile) {
            $asset = File::query()->create([
                'name' => pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME),
                'user_id' => $userId,
            ]);
            $media = $asset->addMedia($upload)->toMediaCollection('default');
            $media->copy($model, $collection);

            return;
        }

        if ($existingFileId) {
            $asset = File::query()->with('media')->find($existingFileId);
            $media = $asset?->getFirstMedia('default');

            if ($media) {
                $media->copy($model, $collection);
            }

            return;
        }

        if ($remove) {
            $model->clearMediaCollection($collection);
        }
    }
}
