<?php

namespace App\Actions\Membership;

use App\Exceptions\MemberAccountLinkException;
use App\Models\Member;
use App\Models\User;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivateMemberSiteAccess
{
    public function __construct(
        private readonly SecureCapabilityAccess $capabilities,
        private readonly LinkUserToMember $link,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $input
     */
    public function handle(string $secret, array $input): User
    {
        return DB::transaction(function () use ($secret, $input) {
            $purpose = (string) config('membership.account_link_capability_purpose', 'member-account-link');
            $grant = $this->capabilities->findLiveBySecret($secret);

            if (
                $grant === null
                || ! $this->capabilities->assertUsable($grant, $purpose, 'member', (int) $grant->target_id)
            ) {
                throw MemberAccountLinkException::invitationUnavailable();
            }

            $payload = is_array($grant->payload) ? $grant->payload : [];

            if (($payload['existing_account'] ?? false) === true || isset($payload['intended_user_id'])) {
                throw MemberAccountLinkException::invitationUnavailable();
            }

            $member = Member::query()->lockForUpdate()->find($grant->target_id);

            if ($member === null || $member->isLinkedToAccount()) {
                throw MemberAccountLinkException::alreadyLinked();
            }

            $email = Str::lower(trim($input['email']));
            $intended = Str::lower(trim((string) ($grant->payload['intended_email'] ?? '')));

            if ($intended !== '' && $email !== $intended) {
                throw MemberAccountLinkException::emailDoesNotMatchInvitation();
            }

            $occupied = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($occupied !== null) {
                throw MemberAccountLinkException::accountAlreadyExists();
            }

            try {
                $user = User::query()->create([
                    'name' => $input['name'],
                    'email' => $email,
                    'password' => $input['password'],
                ]);
            } catch (QueryException) {
                throw MemberAccountLinkException::accountAlreadyExists();
            }

            $this->link->handle($user, $grant, asNewAccountActivation: true);

            return $user->fresh();
        });
    }
}
