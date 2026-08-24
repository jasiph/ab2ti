<?php

namespace App\Actions\Membership;

use App\Enums\MembershipApplicationStatus;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Support\Membership\MembershipApplicationLifecycle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ApproveMembershipApplication
{
    public function __construct(
        private MembershipApplicationLifecycle $lifecycle,
        private CreateAb2tiMemberFromApplication $createMember,
    ) {}

    public function handle(MembershipApplication $application, User $actor): Member
    {
        return DB::transaction(function () use ($application, $actor) {
            /** @var MembershipApplication $application */
            $application = MembershipApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($application->status === MembershipApplicationStatus::Approved) {
                return $application->member()->firstOrFail();
            }

            $existing = Member::query()
                ->where('source_application_id', $application->id)
                ->first();

            if ($existing !== null) {
                $this->markApproved($application, $actor, $existing);

                return $existing;
            }

            $recognizedAt = now();

            try {
                $member = $this->createMember->handle($application, $actor, $recognizedAt);
            } catch (UniqueConstraintViolationException) {
                $member = Member::query()
                    ->where('source_application_id', $application->id)
                    ->firstOrFail();
            }

            $this->markApproved($application, $actor, $member);

            return $member;
        });
    }

    private function markApproved(MembershipApplication $application, User $actor, Member $member): void
    {
        if ($application->status === MembershipApplicationStatus::Approved) {
            return;
        }

        $this->lifecycle->transition(
            $application,
            MembershipApplicationStatus::Approved,
            $actor,
            'staff',
            null,
            function (MembershipApplication $application) use ($actor, $member): void {
                $application->approved_at = $member->recognized_at ?? now();
                $application->approved_by = $actor->id;
            },
        );
    }
}
