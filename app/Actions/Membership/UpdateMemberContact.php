<?php

namespace App\Actions\Membership;

use App\Models\Member;

class UpdateMemberContact
{
    /**
     * @param  array{legal_name: string, email: string, phone: string, address: string}  $payload
     */
    public function handle(Member $member, array $payload): Member
    {
        $member->legal_name = $payload['legal_name'];
        $member->email = $payload['email'];
        $member->phone = $payload['phone'];
        $member->address = $payload['address'];
        $member->save();

        return $member->fresh() ?? $member;
    }
}
