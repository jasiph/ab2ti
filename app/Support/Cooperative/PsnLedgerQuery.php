<?php

namespace App\Support\Cooperative;

use App\Enums\CooperativeMembershipStatus;
use App\Models\CooperativeBusiness;
use Illuminate\Support\Facades\DB;

final class PsnLedgerQuery
{
    /**
     * Resolved PSN position for active Cooperative memberships.
     * Current units = SUM(participation_entries.units). Participants have SUM > 0.
     *
     * @return array{participants: int, units: int}
     */
    public static function resolvedTotalsForActiveMembers(?CooperativeBusiness $business): array
    {
        if ($business === null) {
            return ['participants' => 0, 'units' => 0];
        }

        $grouped = DB::table('business_participations as bp')
            ->join('cooperative_memberships as cm', 'cm.id', '=', 'bp.cooperative_membership_id')
            ->join('participation_entries as pe', 'pe.business_participation_id', '=', 'bp.id')
            ->where('bp.cooperative_business_id', $business->id)
            ->where('cm.status', CooperativeMembershipStatus::Active->value)
            ->groupBy('bp.id')
            ->havingRaw('SUM(pe.units) > 0')
            ->selectRaw('bp.id, SUM(pe.units) as units_sum');

        $stats = DB::query()
            ->fromSub($grouped, 'resolved_psn')
            ->selectRaw('COUNT(*) as participants, COALESCE(SUM(units_sum), 0) as units')
            ->first();

        return [
            'participants' => (int) ($stats->participants ?? 0),
            'units' => (int) ($stats->units ?? 0),
        ];
    }

    /**
     * Membership IDs whose PSN ledger currently sums to more than zero.
     *
     * @return list<int>
     */
    public static function membershipIdsWithResolvedUnits(?CooperativeBusiness $business): array
    {
        if ($business === null) {
            return [];
        }

        return DB::table('business_participations as bp')
            ->join('participation_entries as pe', 'pe.business_participation_id', '=', 'bp.id')
            ->where('bp.cooperative_business_id', $business->id)
            ->groupBy('bp.cooperative_membership_id')
            ->havingRaw('SUM(pe.units) > 0')
            ->pluck('bp.cooperative_membership_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
