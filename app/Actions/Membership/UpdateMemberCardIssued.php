<?php

namespace App\Actions\Membership;

use App\Models\Member;

/**
 * Compatibility writer for the deferred Kartu Anggota flag.
 *
 * Phase 3E staff HTTP no longer calls this. The column remains on
 * `members` for schema compatibility. Do not use this as RC card issuance.
 */
class UpdateMemberCardIssued
{
    public function handle(Member $member, bool $cardIssued): Member
    {
        $member->card_issued = $cardIssued;
        $member->card_issued_at = $cardIssued ? ($member->card_issued_at ?? now()) : null;
        $member->save();

        return $member;
    }
}
