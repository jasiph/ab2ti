<?php

namespace App\Actions\Lapak;

use App\Enums\LapakModerationStatus;
use App\Exceptions\InvalidLapakTransition;
use App\Models\LapakListing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransitionLapakListingModeration
{
    public function handle(
        LapakListing $listing,
        LapakModerationStatus $next,
        User $actor,
        ?string $note = null,
    ): LapakListing {
        return DB::transaction(function () use ($listing, $next, $actor, $note) {
            $listing = LapakListing::query()->lockForUpdate()->findOrFail($listing->id);

            if ($listing->moderation_status === $next) {
                return $listing;
            }

            if (! $listing->moderation_status->canTransitionTo($next)) {
                throw new InvalidLapakTransition(
                    'Status produk tidak dapat diubah ke '.$next->label().' dari '.$listing->moderation_status->label().'.',
                );
            }

            if ($next === LapakModerationStatus::Published) {
                $this->assertPublishable($listing);
                $listing->published_at = $listing->published_at ?? now();
            }

            if (in_array($next, [LapakModerationStatus::Rejected, LapakModerationStatus::Suspended], true) && blank($note)) {
                throw new InvalidLapakTransition(
                    'Berikan alasan saat menolak atau menangguhkan produk.',
                );
            }

            $listing->moderation_status = $next;
            $listing->moderation_note = in_array($next, [LapakModerationStatus::Rejected, LapakModerationStatus::Suspended], true)
                ? $note
                : null;
            $listing->updated_by = $actor->id;
            $listing->save();

            return $listing;
        });
    }

    private function assertPublishable(LapakListing $listing): void
    {
        $listing->loadMissing('category');

        if (blank($listing->title) || blank($listing->slug)) {
            throw new InvalidLapakTransition('Produk harus memiliki judul dan slug sebelum tayang.');
        }

        if ($listing->category === null || ! $listing->category->is_active) {
            throw new InvalidLapakTransition('Produk harus memakai kategori yang aktif sebelum tayang.');
        }
    }
}
