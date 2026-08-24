<?php

namespace App\Actions\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Support\Membership\MembershipApplicationLifecycle;
use Illuminate\Support\Facades\DB;

class RejectMembershipApplication
{
    public function __construct(
        private MembershipApplicationLifecycle $lifecycle,
    ) {}

    public function handle(MembershipApplication $application, User $actor, ?string $reason = null): MembershipApplication
    {
        return DB::transaction(function () use ($application, $actor, $reason) {
            /** @var MembershipApplication $application */
            $application = MembershipApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($application->status === MembershipApplicationStatus::Rejected) {
                return $application;
            }

            $this->lifecycle->transition(
                $application,
                MembershipApplicationStatus::Rejected,
                $actor,
                'staff',
                $reason,
                function (MembershipApplication $application) use ($actor, $reason): void {
                    $application->rejected_at = now();
                    $application->rejected_by = $actor->id;
                    $application->rejection_reason = $reason;
                },
            );

            return $application;
        });
    }
}
