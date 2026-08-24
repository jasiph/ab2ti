<?php

namespace App\Actions\Membership;

use App\Exceptions\MemberAccountLinkException;
use App\Models\Member;
use App\Models\User;
use App\Support\SecureCapability\IssuedCapability;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Illuminate\Support\Str;

class IssueMemberAccountLink
{
    public function __construct(private readonly SecureCapabilityAccess $capabilities) {}

    public function handle(Member $member, User $issuer, string $intendedEmail): IssuedCapability
    {
        if ($member->isLinkedToAccount()) {
            throw MemberAccountLinkException::alreadyLinked();
        }

        $email = Str::lower(trim($intendedEmail));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw MemberAccountLinkException::invalidAccountEmail();
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing?->isPlatformStaff()) {
            throw MemberAccountLinkException::staffAccountCannotBeInvited();
        }

        if ($existing?->linkedMember() !== null) {
            throw MemberAccountLinkException::userAlreadyLinked();
        }

        $purpose = (string) config('membership.account_link_capability_purpose', 'member-account-link');

        $payload = [
            'intended_email' => $email,
            'existing_account' => $existing !== null,
        ];

        if ($existing !== null) {
            $payload['intended_user_id'] = $existing->id;
        }

        return $this->capabilities->issue(
            $purpose,
            'member',
            $member->id,
            $issuer,
            null,
            $payload,
        );
    }
}
