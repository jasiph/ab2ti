<?php

namespace App\Actions\Membership;

use App\Exceptions\MemberAccountLinkException;
use App\Models\Member;
use App\Models\SecureCapabilityGrant;
use App\Models\User;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Illuminate\Support\Facades\DB;

class LinkUserToMember
{
    public function __construct(private readonly SecureCapabilityAccess $capabilities) {}

    public function handle(User $user, SecureCapabilityGrant $grant, bool $asNewAccountActivation = false): Member
    {
        return DB::transaction(function () use ($user, $grant, $asNewAccountActivation) {
            $purpose = (string) config('membership.account_link_capability_purpose', 'member-account-link');

            if (! $this->capabilities->assertUsable($grant, $purpose, 'member', (int) $grant->target_id)) {
                throw MemberAccountLinkException::invitationUnavailable();
            }

            if ($user->isPlatformStaff()) {
                throw MemberAccountLinkException::staffCannotClaim();
            }

            $member = Member::query()->lockForUpdate()->find($grant->target_id);
            $account = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($member === null) {
                throw MemberAccountLinkException::invitationUnavailable();
            }

            if ($member->isLinkedToAccount()) {
                throw MemberAccountLinkException::alreadyLinked();
            }

            if ($account->linkedMember() !== null) {
                throw MemberAccountLinkException::userAlreadyLinked();
            }

            $this->assertInvitationMatchesAccount($grant, $account, $asNewAccountActivation);

            $member->user_id = $account->id;
            $member->linked_at = now();
            $member->save();

            $this->capabilities->consume($grant);

            return $member->fresh();
        });
    }

    private function assertInvitationMatchesAccount(
        SecureCapabilityGrant $grant,
        User $account,
        bool $asNewAccountActivation,
    ): void {
        $payload = is_array($grant->payload) ? $grant->payload : [];
        $existingAccount = ($payload['existing_account'] ?? false) === true;
        $intendedUserId = $this->intendedUserId($payload);

        if ($asNewAccountActivation) {
            if ($existingAccount || $intendedUserId !== null) {
                throw MemberAccountLinkException::invitationUnavailable();
            }

            return;
        }

        if (! $existingAccount || $intendedUserId === null || $intendedUserId !== (int) $account->id) {
            throw MemberAccountLinkException::invitationUnavailable();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function intendedUserId(array $payload): ?int
    {
        $id = $payload['intended_user_id'] ?? null;

        if (is_int($id) && $id > 0) {
            return $id;
        }

        if (is_string($id) && ctype_digit($id) && (int) $id > 0) {
            return (int) $id;
        }

        return null;
    }
}
