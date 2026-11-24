<?php

namespace App\Support\Cooperative;

use App\Enums\CooperativeFundType;
use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewStatus;
use App\Enums\CooperativeMembershipStatus;
use App\Enums\PsnAdjustmentReason;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeFundEntry;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeImportRow;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Support\Cooperative\Import\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;

final class CooperativeViews
{
    /**
     * @return array<string, mixed>
     */
    public static function adminListItem(CooperativeMembership $membership): array
    {
        $member = $membership->member;
        $psn = self::psnSummaryForMembership($membership);
        $review = self::reviewIndicatorsForMember($member);

        return [
            'id' => $membership->id,
            'status' => $membership->status->value,
            'joined_at' => $membership->joined_at?->toIso8601String(),
            'ended_at' => $membership->ended_at?->toIso8601String(),
            'psn_units' => $psn['units'],
            'psn_state' => $psn['state'],
            'has_psn' => $psn['has_psn'],
            'reconciliation_pending' => $psn['reconciliation_pending'],
            'review_pending' => $review['pending'],
            'review_badges' => $review['badges'],
            'has_user_account' => $member?->user_id !== null,
            'member' => $member === null ? null : [
                'id' => $member->id,
                'legal_name' => $member->legal_name,
                'membership_number' => $member->membership_number,
                'email' => $member->email,
                'phone' => $member->phone,
            ],
        ];
    }

    /**
     * Unified operational profile for Cooperative administration.
     *
     * @return array<string, mixed>
     */
    public static function adminDetail(CooperativeMembership $membership): array
    {
        $membership->loadMissing(['member.account', 'fundEntries', 'psnParticipation.entries', 'psnParticipation.business']);
        $member = $membership->member;
        $psn = self::psnSummaryForMembership($membership, includeHistory: true);
        $review = self::reviewIndicatorsForMember($member);

        $funds = $membership->fundEntries
            ->filter(fn (CooperativeFundEntry $entry) => $entry->reversed_at === null)
            ->map(fn (CooperativeFundEntry $entry) => [
                'fund_type' => $entry->fund_type->value,
                'amount' => (int) $entry->amount,
                'amount_label' => IdrMoney::format((int) $entry->amount),
                'source_type' => $entry->source_type->value,
                'effective_date_start' => $entry->effective_date_start?->toDateString(),
            ])
            ->values()
            ->all();

        $principal = collect($funds)->firstWhere('fund_type', CooperativeFundType::PrincipalSaving->value);
        $voluntary = collect($funds)->firstWhere('fund_type', CooperativeFundType::VoluntarySaving->value);

        return [
            'id' => $membership->id,
            'status' => $membership->status->value,
            'joined_at' => $membership->joined_at?->toDateString(),
            'ended_at' => $membership->ended_at?->toDateString(),
            'notes' => $membership->notes,
            'member' => $member === null ? null : [
                'id' => $member->id,
                'legal_name' => $member->legal_name,
                'membership_number' => $member->membership_number,
                'email' => $member->email,
                'phone' => $member->phone,
                'address' => $member->address,
                'has_user_account' => $member->user_id !== null,
                'user_name' => $member->account?->name,
            ],
            'psn' => $psn,
            'historical_funds' => [
                'principal' => $principal,
                'voluntary' => $voluntary,
                'items' => $funds,
                'are_source_of_participation' => true,
                'not_current_savings_balance' => true,
            ],
            'review' => $review,
            'adjustment_reasons' => array_map(
                fn (PsnAdjustmentReason $reason) => $reason->value,
                PsnAdjustmentReason::cases(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function psnSummaryForMembership(CooperativeMembership $membership, bool $includeHistory = false): array
    {
        $business = CooperativeBusiness::query()->psn()->first();
        if ($business === null) {
            return [
                'has_psn' => false,
                'state' => 'confirmed_none',
                'units' => 0,
                'unit_value' => 0,
                'value' => 0,
                'history' => [],
                'correctable_entries' => [],
                'reconciliation_pending' => false,
            ];
        }

        $participation = $membership->relationLoaded('psnParticipation')
            ? $membership->psnParticipation
            : $membership->psnParticipation()->with(['entries', 'business'])->first();

        if ($participation !== null && ! $participation->relationLoaded('entries')) {
            $participation->load('entries');
        }

        $units = $participation?->currentUnits() ?? 0;
        $unitValue = (int) $business->current_unit_value;
        $history = [];
        $lastAdjustment = null;
        $correctableEntries = [];

        if ($includeHistory && $participation !== null) {
            $history = $participation->entries
                ->sortByDesc('id')
                ->values()
                ->map(function (ParticipationEntry $entry) {
                    $origin = $entry->provenance['origin'] ?? null;

                    return [
                        ...CooperativePsnViews::adminHistoryItem($entry),
                        'reason_code' => $entry->provenance['reason_code'] ?? null,
                        'operator_id' => $entry->provenance['operator_id'] ?? null,
                        'previous_units' => $entry->provenance['previous_units'] ?? null,
                        'resulting_units' => $entry->provenance['resulting_units'] ?? null,
                        'origin' => $origin,
                        'correction_of' => $entry->provenance['correction_of'] ?? null,
                        'label' => match (true) {
                            ($entry->units_origin?->value ?? null) === 'manual_reconciliation' => 'manual_reconciliation',
                            $entry->source_type->value === 'legacy_import' && $entry->entry_type->value === 'opening_balance' => 'legacy_opening',
                            $origin === 'correction' => 'correction_adjustment',
                            $entry->source_type->value === 'staff' => 'administrative_adjustment',
                            $entry->source_type->value === 'import_reversal' => 'import_reversal',
                            default => $entry->entry_type->value,
                        },
                    ];
                })
                ->all();

            $correctableEntries = collect($history)
                ->where('source_type', 'staff')
                ->map(fn (array $entry) => [
                    'id' => $entry['id'],
                    'units' => $entry['units'],
                    'effective_date_start' => $entry['effective_date_start'],
                    'label' => $entry['label'],
                ])
                ->values()
                ->all();

            $lastAdjustment = collect($history)->firstWhere('entry_type', 'adjustment');
        }

        $reconciliationPending = PsnAdjustmentEligibility::hasUnresolvedPsn($membership);
        $state = match (true) {
            $reconciliationPending => 'unresolved',
            $units > 0 => 'has_shares',
            default => 'confirmed_none',
        };

        return [
            'participation_id' => $participation?->id,
            'has_psn' => $units > 0,
            'state' => $state,
            'units' => $reconciliationPending ? null : $units,
            'unit_value' => $unitValue,
            'unit_value_label' => IdrMoney::format($unitValue),
            'value' => $reconciliationPending ? null : $units * $unitValue,
            'value_label' => $reconciliationPending ? null : IdrMoney::format($units * $unitValue),
            'share_reference' => $participation?->latestShareReference(),
            'last_adjustment' => $lastAdjustment,
            'reconciliation_pending' => $reconciliationPending,
            'correctable_entries' => $correctableEntries,
            'history' => $history,
            'business_label' => $business->publicLabel(),
        ];
    }

    /**
     * @return array{pending: bool, badges: list<array{key: string, label_id: string, label_en: string}>, reconciliation_batch_id: ?int}
     */
    public static function reviewIndicatorsForMember(?Member $member): array
    {
        if ($member === null) {
            return ['pending' => false, 'badges' => [], 'reconciliation_batch_id' => null];
        }

        $items = CooperativeImportReviewItem::query()
            ->where('status', CooperativeImportReviewStatus::Pending)
            ->whereHas('importRow', function (Builder $query) use ($member): void {
                $query->where('created_member_id', $member->id)
                    ->orWhere('matched_member_id', $member->id);
            })
            ->get();

        if ($items->isEmpty()) {
            return ['pending' => false, 'badges' => [], 'reconciliation_batch_id' => null];
        }

        $badges = [];
        $map = [
            CooperativeImportReviewCategory::PsnUnconfirmed->value => ['PSN perlu dikonfirmasi', 'PSN needs confirmation'],
            CooperativeImportReviewCategory::ProfileIncomplete->value => ['Profil belum lengkap', 'Incomplete profile'],
            CooperativeImportReviewCategory::SharedEmail->value => ['Email digunakan bersama', 'Shared email'],
            CooperativeImportReviewCategory::SharedPhone->value => ['Telepon digunakan bersama', 'Shared phone'],
            CooperativeImportReviewCategory::DuplicateNik->value => ['Dugaan NIK ganda', 'Possible duplicate NIK'],
            CooperativeImportReviewCategory::DuplicatePerson->value => ['Dugaan orang yang sama', 'Possible same person'],
        ];

        foreach ($items->groupBy(fn ($item) => $item->category->value) as $category => $group) {
            if (! isset($map[$category])) {
                continue;
            }
            $badges[] = [
                'key' => $category,
                'label_id' => $map[$category][0],
                'label_en' => $map[$category][1],
                'count' => $group->count(),
            ];
        }

        return [
            'pending' => true,
            'badges' => $badges,
            'reconciliation_batch_id' => $items->first()?->batch_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function memberCandidate(Member $member): array
    {
        return [
            'id' => $member->id,
            'legal_name' => $member->legal_name,
            'membership_number' => $member->membership_number,
        ];
    }

    /**
     * @return array{state: string, joined_at: ?string, id: ?int}
     */
    public static function badgeForMember(Member $member): array
    {
        $membership = $member->cooperativeMembership;

        if ($membership === null) {
            return [
                'id' => null,
                'state' => 'none',
                'joined_at' => null,
            ];
        }

        return [
            'id' => $membership->id,
            'state' => $membership->status->value,
            'joined_at' => $membership->joined_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function workspaceStatus(?Member $member): array
    {
        if ($member === null) {
            return [
                'state' => 'none',
                'joined_at' => null,
                'psn' => CooperativePsnViews::workspaceParticipation(null),
            ];
        }

        $badge = self::badgeForMember($member);
        $badge['psn'] = CooperativePsnViews::workspaceParticipation($member);

        return $badge;
    }

    /**
     * @return list<string>
     */
    public static function statusValues(): array
    {
        return array_map(
            fn (CooperativeMembershipStatus $status) => $status->value,
            CooperativeMembershipStatus::cases(),
        );
    }

    public static function applyAdminListFilters(Builder $query, string $search, string $status, string $psn, string $review): Builder
    {
        $query
            ->when($search !== '', function ($builder) use ($search) {
                $builder->whereHas('member', function ($memberQuery) use ($search) {
                    self::applyMemberSearch($memberQuery, $search);
                });
            })
            ->when(
                in_array($status, self::statusValues(), true),
                fn ($builder) => $builder->where('status', $status),
            );

        if ($psn === 'has_psn' || $psn === 'no_psn') {
            $havingPsn = PsnLedgerQuery::membershipIdsWithResolvedUnits(
                CooperativeBusiness::query()->psn()->first(),
            );

            if ($psn === 'has_psn') {
                $query->whereIn('id', $havingPsn);
            } else {
                $query->whereNotIn('id', $havingPsn);
            }
        }

        if ($review === 'pending' || $review === 'psn_pending' || $review === 'profile_incomplete') {
            $memberIds = CooperativeImportRow::query()
                ->whereHas('batch')
                ->where(function ($rowQuery) use ($review): void {
                    $rowQuery->whereHas('reviewItems', function ($itemQuery) use ($review): void {
                        $itemQuery->where('status', CooperativeImportReviewStatus::Pending);
                        if ($review === 'psn_pending') {
                            $itemQuery->where('category', CooperativeImportReviewCategory::PsnUnconfirmed);
                        }
                        if ($review === 'profile_incomplete') {
                            $itemQuery->where('category', CooperativeImportReviewCategory::ProfileIncomplete);
                        }
                    });
                })
                ->get()
                ->map(fn (CooperativeImportRow $row) => $row->created_member_id ?? $row->matched_member_id)
                ->filter()
                ->unique()
                ->values();

            $query->whereIn('member_id', $memberIds);
        }

        return $query;
    }

    public static function applyMemberSearch(Builder $memberQuery, string $search): Builder
    {
        $term = trim(preg_replace('/\s+/', ' ', $search) ?? '');
        if ($term === '') {
            return $memberQuery;
        }

        $like = '%'.$term.'%';
        $lower = mb_strtolower($term);
        $normalizedPhone = (new PhoneNormalizer)->normalize($term)['normalized'];
        $digits = preg_replace('/\D+/', '', $term) ?: null;

        return $memberQuery->where(function (Builder $query) use ($like, $lower, $normalizedPhone, $digits): void {
            $query->where('legal_name', 'like', $like)
                ->orWhereRaw('LOWER(legal_name) LIKE ?', ['%'.$lower.'%'])
                ->orWhere('email', 'like', $like)
                ->orWhereRaw('LOWER(COALESCE(email, "")) LIKE ?', ['%'.$lower.'%'])
                ->orWhere('membership_number', 'like', $like)
                ->orWhere('phone', 'like', $like);

            if ($normalizedPhone !== null) {
                $query->orWhere('phone', 'like', '%'.$normalizedPhone.'%')
                    ->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '+', ''), '.', '') LIKE ?",
                        ['%'.$normalizedPhone.'%'],
                    );
            }

            if ($digits !== null && strlen($digits) >= 6) {
                $localDigits = str_starts_with($digits, '62') ? '0'.substr($digits, 2) : $digits;
                $query->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '+', ''), '.', '') LIKE ?",
                    ['%'.$digits.'%'],
                )->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '-', ''), '+', ''), '.', '') LIKE ?",
                    ['%'.$localDigits.'%'],
                );
            }
        });
    }
}
