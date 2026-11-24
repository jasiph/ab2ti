<?php

namespace App\Support\Cooperative;

use App\Enums\CooperativeMembershipStatus;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeMembership;
use App\Models\Member;

final class CooperativePublicStats
{
    /**
     * Approved public aggregates only. PSN figures come from committed ledger
     * rows for active Cooperative memberships. Staging is never counted.
     *
     * @return array{
     *     member_registry_complete: bool,
     *     psn_legacy_reconciliation_complete: bool,
     *     total_ab2ti_members: ?int,
     *     active_cooperative_members: int,
     *     participation_percentage: ?float,
     *     total_psn_shares: ?int,
     *     psn_participants: ?int
     * }
     */
    public function toArray(): array
    {
        $registryComplete = (bool) config('cooperative.ab2ti_member_registry_complete', false);
        $psnComplete = (bool) config('cooperative.psn_legacy_reconciliation_complete', false);
        $totalAb2tiMembers = Member::query()->count();
        $activeCooperativeMembers = CooperativeMembership::query()
            ->where('status', CooperativeMembershipStatus::Active)
            ->count();

        $participationPercentage = $totalAb2tiMembers === 0
            ? 0.0
            : round(($activeCooperativeMembers / $totalAb2tiMembers) * 100, 1);

        [$totalPsnShares, $psnParticipants] = $this->psnAggregates();

        return [
            'member_registry_complete' => $registryComplete,
            'psn_legacy_reconciliation_complete' => $psnComplete,
            'total_ab2ti_members' => $registryComplete ? $totalAb2tiMembers : null,
            'active_cooperative_members' => $activeCooperativeMembers,
            'participation_percentage' => $registryComplete ? (float) $participationPercentage : null,
            'total_psn_shares' => $psnComplete ? $totalPsnShares : null,
            'psn_participants' => $psnComplete ? $psnParticipants : null,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function psnAggregates(): array
    {
        $business = CooperativeBusiness::query()->psn()->first();

        if ($business === null) {
            return [0, 0];
        }

        $participations = BusinessParticipation::query()
            ->where('cooperative_business_id', $business->id)
            ->whereHas('membership', fn ($query) => $query->where('status', CooperativeMembershipStatus::Active))
            ->with('entries')
            ->get();

        $shares = 0;
        $participants = 0;

        foreach ($participations as $participation) {
            $units = $participation->currentUnits();
            if ($units <= 0) {
                continue;
            }
            $shares += $units;
            $participants++;
        }

        return [$shares, $participants];
    }
}
