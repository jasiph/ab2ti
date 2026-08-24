<?php

namespace App\Support\Publishing;

use App\Models\File;
use App\Models\Publication;
use Illuminate\Http\UploadedFile;

final class PublicationMedia
{
    public function syncCover(
        Publication $publication,
        ?UploadedFile $upload,
        ?int $existingFileId,
        bool $remove,
        int $userId,
    ): void {
        $this->syncCollection(
            $publication,
            'publication-cover',
            $upload,
            $existingFileId,
            $remove,
            $userId,
        );
    }

    public function syncSocialImage(
        Publication $publication,
        ?UploadedFile $upload,
        ?int $existingFileId,
        bool $remove,
        int $userId,
    ): void {
        $this->syncCollection(
            $publication,
            'social-og',
            $upload,
            $existingFileId,
            $remove,
            $userId,
        );
    }

    private function syncCollection(
        Publication $publication,
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
            $media->copy($publication, $collection);

            return;
        }

        if ($existingFileId) {
            $asset = File::query()->with('media')->find($existingFileId);
            $media = $asset?->getFirstMedia('default');

            if ($media) {
                $media->copy($publication, $collection);
            }

            return;
        }

        if ($remove) {
            $publication->clearMediaCollection($collection);
        }
    }
}
