<?php

namespace App\Actions\Publishing;

use App\Enums\PublicationStatus;
use App\Exceptions\InvalidPublicationTransition;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnpublishPublication
{
    public function handle(Publication $publication, User $actor): Publication
    {
        return DB::transaction(function () use ($publication, $actor) {
            /** @var Publication $publication */
            $publication = Publication::query()->lockForUpdate()->findOrFail($publication->id);

            if ($publication->status === PublicationStatus::Draft) {
                return $publication;
            }

            if (! $publication->status->canTransitionTo(PublicationStatus::Draft)) {
                throw new InvalidPublicationTransition(
                    'Publikasi ini tidak dapat dibatalkan terbitannya pada status saat ini.',
                );
            }

            $publication->status = PublicationStatus::Draft;
            $publication->updated_by = $actor->id;
            $publication->save();

            return $publication;
        });
    }
}
