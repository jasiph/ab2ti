<?php

namespace App\Support\Membership;

use App\Models\Member;
use App\Models\SecureCapabilityGrant;

final class AccountLinkStatus
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Member $member): array
    {
        if ($member->isLinkedToAccount()) {
            $member->loadMissing('account');

            return [
                'status' => 'linked',
                'label' => 'Aktif',
                'linked_user_name' => $member->account?->name,
                'linked_user_email' => $member->account?->email,
                'linked_at' => $member->linked_at?->toIso8601String(),
                'invitation_expires_at' => null,
                'intended_email' => null,
                'existing_account' => false,
                'revoked' => false,
            ];
        }

        $purpose = (string) config('membership.account_link_capability_purpose', 'member-account-link');
        $live = SecureCapabilityGrant::query()
            ->where('purpose', $purpose)
            ->where('target_type', 'member')
            ->where('target_id', $member->id)
            ->whereNull('revoked_at')
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($live && $live->isLive()) {
            $intended = self::intendedEmail($live);
            $existing = (($live->payload['existing_account'] ?? false) === true);

            return [
                'status' => 'invited',
                'label' => 'Undangan aktivasi tersedia',
                'linked_user_name' => null,
                'linked_user_email' => null,
                'linked_at' => null,
                'invitation_expires_at' => $live->expires_at?->toIso8601String(),
                'intended_email' => $intended,
                'existing_account' => $existing,
                'revoked' => false,
            ];
        }

        $stale = SecureCapabilityGrant::query()
            ->where('purpose', $purpose)
            ->where('target_type', 'member')
            ->where('target_id', $member->id)
            ->latest('id')
            ->first();

        if ($stale && ($stale->isExpired() || $stale->isRevoked()) && ! $stale->isConsumed()) {
            return [
                'status' => 'expired',
                'label' => $stale->isRevoked() ? 'Undangan dicabut' : 'Undangan kedaluwarsa',
                'linked_user_name' => null,
                'linked_user_email' => null,
                'linked_at' => null,
                'invitation_expires_at' => $stale->expires_at?->toIso8601String(),
                'intended_email' => self::intendedEmail($stale),
                'existing_account' => false,
                'revoked' => $stale->isRevoked(),
            ];
        }

        return [
            'status' => 'unlinked',
            'label' => 'Belum aktif',
            'linked_user_name' => null,
            'linked_user_email' => null,
            'linked_at' => null,
            'invitation_expires_at' => null,
            'intended_email' => null,
            'existing_account' => false,
            'revoked' => false,
        ];
    }

    private static function intendedEmail(?SecureCapabilityGrant $grant): ?string
    {
        $email = $grant?->payload['intended_email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
