<?php

namespace App\Actions\Membership;

use App\Models\Member;
use App\Support\SecureCapability\SecureCapabilityAccess;

class RevokeMemberAccountLink
{
    public function __construct(private readonly SecureCapabilityAccess $capabilities) {}

    public function handle(Member $member): void
    {
        $purpose = (string) config('membership.account_link_capability_purpose', 'member-account-link');

        $this->capabilities->revokeLiveSiblings($purpose, 'member', $member->id);
    }
}
