<?php

namespace App\Actions\Publishing;

use App\Enums\PublicationStatus;
use App\Exceptions\InvalidPublicationTransition;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishPublication
{
    public function handle(Publication $publication, User $actor): Publication
    {
        return DB::transaction(function () use ($publication, $actor) {
            /** @var Publication $publication */
            $publication = Publication::query()->lockForUpdate()->findOrFail($publication->id);

            if ($publication->status === PublicationStatus::Published) {
                return $publication;
            }

            if (! $publication->status->canTransitionTo(PublicationStatus::Published)) {
                throw new InvalidPublicationTransition(
                    'Publikasi ini tidak dapat diterbitkan pada status saat ini.',
                );
            }

            if (blank($publication->title) || blank($publication->slug)) {
                throw new InvalidPublicationTransition(
                    'Publikasi harus memiliki judul dan slug sebelum diterbitkan.',
                );
            }

            $publication->status = PublicationStatus::Published;
            $publication->published_at = $publication->published_at ?? now();
            $publication->updated_by = $actor->id;
            $publication->save();

            return $publication;
        });
    }
}
