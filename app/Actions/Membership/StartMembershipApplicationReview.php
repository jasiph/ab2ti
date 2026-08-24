<?php

namespace App\Actions\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Exceptions\InvalidMembershipApplicationTransition;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Support\Membership\MembershipApplicationLifecycle;
use Illuminate\Support\Facades\DB;

class StartMembershipApplicationReview
{
    public function __construct(
        private MembershipApplicationLifecycle $lifecycle,
    ) {}

    public function handle(MembershipApplication $application, User $actor, ?string $staffNotes = null): MembershipApplication
    {
        return DB::transaction(function () use ($application, $actor, $staffNotes) {
            /** @var MembershipApplication $application */
            $application = MembershipApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($application->status === MembershipApplicationStatus::UnderReview) {
                if ($staffNotes !== null) {
                    $application->staff_notes = $staffNotes;
                    $application->save();
                }

                return $application;
            }

            if ($application->status !== MembershipApplicationStatus::Submitted) {
                throw new InvalidMembershipApplicationTransition(
                    'Permohonan ini tidak dapat mulai ditinjau pada status saat ini.',
                );
            }

            $this->lifecycle->transition(
                $application,
                MembershipApplicationStatus::UnderReview,
                $actor,
                'staff',
                $staffNotes,
                function (MembershipApplication $application) use ($actor, $staffNotes): void {
                    $application->review_started_at = now();
                    $application->reviewed_by = $actor->id;

                    if ($staffNotes !== null) {
                        $application->staff_notes = $staffNotes;
                    }
                },
            );

            return $application;
        });
    }
}
