<?php

namespace App\Actions\Membership;

use App\Enums\MemberSourceType;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Support\Membership\MembershipNumberGenerator;
use DateTimeInterface;

class CreateAb2tiMemberFromApplication
{
    /**
     * Snapshot applicant contact onto a new AB2TI Member.
     *
     * Does not create a User, assign a Spatie role, or write Koperasi fields.
     * Eligibility and application status remain the caller's responsibility
     * (ApproveMembershipApplication).
     */
    public function handle(
        MembershipApplication $application,
        User $actor,
        DateTimeInterface $recognizedAt,
    ): Member {
        $member = new Member;
        $member->membership_number = MembershipNumberGenerator::generate();
        $member->source_application_id = $application->id;
        $member->source_type = MemberSourceType::MembershipApplication;
        $member->status = MemberStatus::Active;
        $member->legal_name = $application->legal_name;
        $member->email = $application->email;
        $member->phone = $application->phone;
        $member->address = $application->address;
        $member->recognized_at = $recognizedAt;
        $member->card_issued = false;
        $member->created_by = $actor->id;
        $member->save();

        return $member;
    }
}
