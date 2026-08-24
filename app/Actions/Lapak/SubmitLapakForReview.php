<?php

namespace App\Actions\Lapak;

use App\Enums\LapakModerationStatus;
use App\Exceptions\InvalidLapakTransition;
use App\Models\Lapak;
use App\Models\User;

class SubmitLapakForReview
{
    public function __construct(private readonly TransitionLapakModeration $transition) {}

    public function handle(Lapak $lapak, User $actor): Lapak
    {
        if (! $actor->ownsLapak($lapak)) {
            throw new InvalidLapakTransition('Anda hanya dapat mengirim Lapak milik sendiri.');
        }

        if (! $lapak->moderation_status->ownerMaySubmitForReview()
            && $lapak->moderation_status !== LapakModerationStatus::Published) {
            throw new InvalidLapakTransition(
                'Lapak tidak dapat dikirim untuk ditinjau pada status '.$lapak->moderation_status->ownerLabel().'.',
            );
        }

        return $this->transition->handle($lapak, LapakModerationStatus::PendingReview, $actor);
    }
}
