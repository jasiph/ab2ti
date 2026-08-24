<?php

namespace App\Support\Knowledge;

use App\Models\File;
use App\Models\KnowledgeArticle;
use Illuminate\Http\UploadedFile;

final class KnowledgeArticleMedia
{
    public function syncCover(
        KnowledgeArticle $article,
        ?UploadedFile $upload,
        ?int $existingFileId,
        bool $remove,
        int $userId,
    ): void {
        $this->syncCollection($article, 'knowledge-cover', $upload, $existingFileId, $remove, $userId);
    }

    public function syncSocialImage(
        KnowledgeArticle $article,
        ?UploadedFile $upload,
        ?int $existingFileId,
        bool $remove,
        int $userId,
    ): void {
        $this->syncCollection($article, 'social-og', $upload, $existingFileId, $remove, $userId);
    }

    private function syncCollection(
        KnowledgeArticle $article,
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
            $media->copy($article, $collection);

            return;
        }

        if ($existingFileId) {
            $asset = File::query()->with('media')->find($existingFileId);
            $media = $asset?->getFirstMedia('default');

            if ($media) {
                $media->copy($article, $collection);
            }

            return;
        }

        if ($remove) {
            $article->clearMediaCollection($collection);
        }
    }
}
