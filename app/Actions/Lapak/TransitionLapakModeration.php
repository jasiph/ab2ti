<?php

namespace App\Actions\Lapak;

use App\Enums\LapakModerationStatus;
use App\Exceptions\InvalidLapakTransition;
use App\Models\Lapak;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransitionLapakModeration
{
    public function handle(
        Lapak $lapak,
        LapakModerationStatus $next,
        User $actor,
        ?string $note = null,
    ): Lapak {
        return DB::transaction(function () use ($lapak, $next, $actor, $note) {
            $lapak = Lapak::query()->lockForUpdate()->findOrFail($lapak->id);

            if ($lapak->moderation_status === $next) {
                return $lapak;
            }

            if (! $lapak->moderation_status->canTransitionTo($next)) {
                throw new InvalidLapakTransition(
                    'Status Lapak tidak dapat diubah ke '.$next->label().' dari '.$lapak->moderation_status->label().'.',
                );
            }

            if ($next === LapakModerationStatus::Published) {
                $this->assertPublishable($lapak);
                $lapak->published_at = $lapak->published_at ?? now();
            }

            if (in_array($next, [LapakModerationStatus::Rejected, LapakModerationStatus::Suspended], true) && blank($note)) {
                throw new InvalidLapakTransition(
                    'Berikan alasan saat menolak atau menangguhkan Lapak.',
                );
            }

            $lapak->moderation_status = $next;
            $lapak->moderation_note = in_array($next, [LapakModerationStatus::Rejected, LapakModerationStatus::Suspended], true)
                ? $note
                : null;
            $lapak->updated_by = $actor->id;
            $lapak->save();

            return $lapak;
        });
    }

    private function assertPublishable(Lapak $lapak): void
    {
        if (blank($lapak->name) || blank($lapak->slug)) {
            throw new InvalidLapakTransition('Lapak harus memiliki nama dan slug sebelum tayang.');
        }

        if (! $lapak->hasContactChannel()) {
            throw new InvalidLapakTransition('Lapak harus memiliki minimal satu saluran kontak sebelum tayang.');
        }
    }
}
