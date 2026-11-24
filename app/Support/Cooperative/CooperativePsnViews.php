<?php

namespace App\Support\Cooperative;

use App\Enums\CooperativeMembershipStatus;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeFundEntry;
use App\Models\Member;
use App\Models\ParticipationEntry;

final class CooperativePsnViews
{
    /**
     * @return array<string, mixed>
     */
    public static function adminSummary(?CooperativeBusiness $business = null): array
    {
        $business ??= CooperativeBusiness::psn();
        $totals = PsnLedgerQuery::resolvedTotalsForActiveMembers($business);
        $currentUnits = $totals['units'];
        $participants = $totals['participants'];
        $unitValue = (int) $business->current_unit_value;

        return [
            'id' => $business->id,
            'code' => $business->code,
            'name' => $business->name,
            'label' => $business->publicLabel(),
            'status' => $business->status->value,
            'participation_unit_name' => $business->participation_unit_name,
            'current_unit_value' => $unitValue,
            'current_unit_value_label' => IdrMoney::format($unitValue),
            'total_participants' => $participants,
            'total_current_units' => $currentUnits,
            'total_current_value' => $currentUnits * $unitValue,
            'total_current_value_label' => IdrMoney::format($currentUnits * $unitValue),
            'historical_funds_are_source_of_participation' => true,
            'totals_exclude_inactive_and_reversed' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminParticipant(BusinessParticipation $participation): array
    {
        $membership = $participation->membership;
        $member = $membership?->member;
        $units = $participation->currentUnits();
        $unitValue = (int) $participation->business->current_unit_value;

        return [
            'id' => $participation->id,
            'units' => $units,
            'unit_value' => $unitValue,
            'value' => $units * $unitValue,
            'value_label' => IdrMoney::format($units * $unitValue),
            'share_reference' => $participation->latestShareReference(),
            'membership_id' => $membership?->id,
            'membership_status' => $membership?->status->value,
            'member' => $member === null ? null : [
                'id' => $member->id,
                'legal_name' => $member->legal_name,
                'membership_number' => $member->membership_number,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminHistoryItem(ParticipationEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_type' => $entry->entry_type->value,
            'units' => (int) $entry->units,
            'unit_value' => (int) $entry->unit_value,
            'unit_value_label' => IdrMoney::format((int) $entry->unit_value),
            'amount' => (int) $entry->amount,
            'amount_label' => IdrMoney::format((int) $entry->amount),
            'effective_date_start' => $entry->effective_date_start?->toDateString(),
            'effective_date_end' => $entry->effective_date_end?->toDateString(),
            'share_reference' => $entry->share_reference,
            'units_origin' => $entry->units_origin?->value,
            'source_type' => $entry->source_type->value,
            'import_batch_id' => $entry->import_batch_id,
            'legacy_source_row_id' => $entry->legacy_source_row_id,
            'notes' => $entry->notes,
        ];
    }

    /**
     * Member-owned read-only payload. Never includes other members or staging JSON.
     *
     * @return array<string, mixed>
     */
    public static function workspaceParticipation(?Member $member): array
    {
        if ($member === null || ! $member->isActiveCooperativeMember()) {
            return [
                'has_membership' => $member?->cooperativeMembership !== null,
                'membership_state' => $member?->cooperativeMembership?->status->value ?? 'none',
                'has_psn' => false,
            ];
        }

        $membership = $member->cooperativeMembership;
        $business = CooperativeBusiness::psn();
        $participation = BusinessParticipation::query()
            ->where('cooperative_membership_id', $membership->id)
            ->where('cooperative_business_id', $business->id)
            ->with('entries')
            ->first();

        $units = $participation?->currentUnits() ?? 0;
        $unitValue = (int) $business->current_unit_value;

        $funds = CooperativeFundEntry::query()
            ->where('cooperative_membership_id', $membership->id)
            ->notReversed()
            ->orderBy('id')
            ->get()
            ->map(fn (CooperativeFundEntry $entry) => [
                'fund_type' => $entry->fund_type->value,
                'amount' => (int) $entry->amount,
                'amount_label' => IdrMoney::format((int) $entry->amount),
                'source_type' => $entry->source_type->value,
                'effective_date_start' => $entry->effective_date_start?->toDateString(),
                'effective_date_end' => $entry->effective_date_end?->toDateString(),
            ])
            ->values()
            ->all();

        $history = $participation === null
            ? []
            : $participation->entries
                ->map(fn (ParticipationEntry $entry) => [
                    'entry_type' => $entry->entry_type->value,
                    'units' => (int) $entry->units,
                    'unit_value' => (int) $entry->unit_value,
                    'unit_value_label' => IdrMoney::format((int) $entry->unit_value),
                    'amount_label' => IdrMoney::format((int) $entry->amount),
                    'share_reference' => $entry->share_reference,
                    'effective_date_start' => $entry->effective_date_start?->toDateString(),
                    'effective_date_end' => $entry->effective_date_end?->toDateString(),
                    'units_origin' => $entry->units_origin?->value,
                    'source_type' => $entry->source_type->value,
                ])
                ->values()
                ->all();

        return [
            'has_membership' => true,
            'membership_state' => CooperativeMembershipStatus::Active->value,
            'has_psn' => $units > 0,
            'units' => $units,
            'unit_name' => $business->participation_unit_name,
            'unit_value' => $unitValue,
            'unit_value_label' => IdrMoney::format($unitValue),
            'value' => $units * $unitValue,
            'value_label' => IdrMoney::format($units * $unitValue),
            'share_reference' => $participation?->latestShareReference(),
            'history' => $history,
            'historical_funds' => $funds,
        ];
    }
}
