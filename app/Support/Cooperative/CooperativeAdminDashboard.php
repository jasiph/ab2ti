<?php

namespace App\Support\Cooperative;

use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewStatus;
use App\Enums\CooperativeMembershipStatus;
use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Enums\ParticipationUnitsOrigin;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportReviewAction;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeMembership;
use App\Models\ParticipationEntry;
use Illuminate\Support\Facades\DB;

final class CooperativeAdminDashboard
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $business = CooperativeBusiness::query()->psn()->first();
        $activeMembers = CooperativeMembership::query()
            ->where('status', CooperativeMembershipStatus::Active)
            ->count();

        $resolved = PsnLedgerQuery::resolvedTotalsForActiveMembers($business);
        $resolvedUnits = $resolved['units'];
        $resolvedParticipants = $resolved['participants'];
        $unitValue = $business ? (int) $business->current_unit_value : 0;

        $pendingPsnRows = (int) CooperativeImportReviewItem::query()
            ->where('category', CooperativeImportReviewCategory::PsnUnconfirmed)
            ->where('status', CooperativeImportReviewStatus::Pending)
            ->selectRaw('count(distinct import_row_id) as aggregate')
            ->value('aggregate');

        $uniqueMembersWithPendingReview = $this->uniqueMembersWithPendingReview();

        $pendingByCategory = CooperativeImportReviewItem::query()
            ->where('status', CooperativeImportReviewStatus::Pending)
            ->select('category', DB::raw('count(*) as flag_count'))
            ->groupBy('category')
            ->pluck('flag_count', 'category')
            ->all();

        $latestBatch = CooperativeImportBatch::query()
            ->whereIn('status', [
                CooperativeImportBatchStatus::Committed,
                CooperativeImportBatchStatus::CompletedWithReview,
                CooperativeImportBatchStatus::Replaced,
            ])
            ->orderByDesc('id')
            ->first();

        $psnComplete = (bool) config('cooperative.psn_legacy_reconciliation_complete', false);
        $registryComplete = (bool) config('cooperative.ab2ti_member_registry_complete', false);

        return [
            'active_cooperative_members' => $activeMembers,
            'resolved_psn_participants' => $resolvedParticipants,
            'resolved_psn_units' => $resolvedUnits,
            'current_unit_value' => $unitValue,
            'current_unit_value_label' => IdrMoney::format($unitValue),
            'resolved_participation_value' => $resolvedUnits * $unitValue,
            'resolved_participation_value_label' => IdrMoney::format($resolvedUnits * $unitValue),
            'pending_psn_reconciliation_rows' => $pendingPsnRows,
            'unique_members_with_pending_review' => $uniqueMembersWithPendingReview,
            'pending_review_flags_by_category' => $pendingByCategory,
            'psn_legacy_reconciliation_complete' => $psnComplete,
            'ab2ti_member_registry_complete' => $registryComplete,
            'metrics_are_resolved_ledger_only' => true,
            'org_wide_ratio_hidden' => ! $registryComplete,
            'latest_import' => $latestBatch === null ? null : [
                'id' => $latestBatch->id,
                'status' => $latestBatch->status->value,
                'completed_at' => $latestBatch->completed_at?->toIso8601String(),
                'original_filename' => $latestBatch->original_filename,
            ],
            'attention' => $this->attentionItems($pendingPsnRows, $pendingByCategory, $latestBatch),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    private function uniqueMembersWithPendingReview(): int
    {
        return (int) DB::table('cooperative_import_review_items as items')
            ->join('cooperative_import_rows as rows', 'rows.id', '=', 'items.import_row_id')
            ->where('items.status', CooperativeImportReviewStatus::Pending->value)
            ->where(function ($query): void {
                $query->whereNotNull('rows.created_member_id')
                    ->orWhereNotNull('rows.matched_member_id');
            })
            ->selectRaw('COUNT(DISTINCT COALESCE(rows.created_member_id, rows.matched_member_id)) as aggregate')
            ->value('aggregate');
    }

    /**
     * @param  array<string, int>  $pendingByCategory
     * @return list<array{key: string, label_id: string, label_en: string, count: int, href: string}>
     */
    private function attentionItems(int $pendingPsnRows, array $pendingByCategory, ?CooperativeImportBatch $latestBatch): array
    {
        $items = [];
        $batchId = $latestBatch?->id;

        if ($pendingPsnRows > 0 && $batchId !== null) {
            $items[] = [
                'key' => 'psn_reconciliation',
                'label_id' => 'PSN belum direkonsiliasi',
                'label_en' => 'PSN not yet reconciled',
                'count' => $pendingPsnRows,
                'href' => route('admin.cooperative.imports.reconciliation.index', [
                    'batch' => $batchId,
                    'psn_review' => 1,
                    'status' => 'pending',
                ]),
            ];
        }

        $profile = (int) ($pendingByCategory[CooperativeImportReviewCategory::ProfileIncomplete->value] ?? 0);
        if ($profile > 0 && $batchId !== null) {
            $items[] = [
                'key' => 'profile',
                'label_id' => 'Profil perlu dilengkapi',
                'label_en' => 'Profile needs completion',
                'count' => $profile,
                'href' => route('admin.cooperative.imports.reconciliation.index', [
                    'batch' => $batchId,
                    'profile_warning' => 1,
                    'status' => 'pending',
                ]),
            ];
        }

        $dup = (int) ($pendingByCategory[CooperativeImportReviewCategory::DuplicateNik->value] ?? 0)
            + (int) ($pendingByCategory[CooperativeImportReviewCategory::DuplicatePerson->value] ?? 0);
        if ($dup > 0 && $batchId !== null) {
            $items[] = [
                'key' => 'duplicate',
                'label_id' => 'Dugaan data ganda',
                'label_en' => 'Possible duplicate data',
                'count' => $dup,
                'href' => route('admin.cooperative.imports.reconciliation.index', [
                    'batch' => $batchId,
                    'duplicate_warning' => 1,
                    'status' => 'pending',
                ]),
            ];
        }

        $shared = (int) ($pendingByCategory[CooperativeImportReviewCategory::SharedEmail->value] ?? 0)
            + (int) ($pendingByCategory[CooperativeImportReviewCategory::SharedPhone->value] ?? 0);
        if ($shared > 0 && $batchId !== null) {
            $items[] = [
                'key' => 'shared_contact',
                'label_id' => 'Kontak digunakan bersama',
                'label_en' => 'Shared contact review',
                'count' => $shared,
                'href' => route('admin.cooperative.imports.reconciliation.index', [
                    'batch' => $batchId,
                    'duplicate_warning' => 1,
                    'status' => 'pending',
                ]),
            ];
        }

        if ($latestBatch !== null && $latestBatch->status === CooperativeImportBatchStatus::CompletedWithReview) {
            $items[] = [
                'key' => 'import_review',
                'label_id' => 'Import/reconciliation menunggu',
                'label_en' => 'Import/reconciliation pending',
                'count' => 1,
                'href' => route('admin.cooperative.imports.reconciliation.index', $latestBatch),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(): array
    {
        $ledger = ParticipationEntry::query()
            ->with(['participation.membership.member'])
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function (ParticipationEntry $entry) {
                $member = $entry->participation?->membership?->member;
                $origin = $entry->provenance['origin'] ?? null;
                $kind = $this->ledgerActivityKind($entry, $origin);

                return [
                    'action' => $kind['action'],
                    'label_id' => $kind['label_id'],
                    'label_en' => $kind['label_en'],
                    'member_name' => $member?->legal_name,
                    'operator_id' => $kind['operator_id'],
                    'timestamp' => $entry->created_at?->toIso8601String(),
                    'detail' => sprintf('%+d unit', (int) $entry->units),
                    'imported' => $kind['imported'],
                ];
            });

        $reviews = CooperativeImportReviewAction::query()
            ->with(['operator', 'importRow.createdMember', 'importRow.matchedMember'])
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (CooperativeImportReviewAction $action) {
                $row = $action->importRow;
                $member = $row?->createdMember ?? $row?->matchedMember;

                return [
                    'action' => 'review_outcome',
                    'label_id' => 'Keputusan validasi',
                    'label_en' => 'Review outcome',
                    'member_name' => $member?->legal_name,
                    'operator_id' => $action->operator_id,
                    'operator_name' => $action->operator?->name,
                    'timestamp' => $action->created_at?->toIso8601String(),
                    'detail' => $action->outcome->value,
                    'imported' => false,
                ];
            });

        $imports = CooperativeImportBatch::query()
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get()
            ->map(fn (CooperativeImportBatch $batch) => [
                'action' => $batch->status === CooperativeImportBatchStatus::Replaced
                    ? 'legacy_replacement'
                    : 'legacy_import',
                'label_id' => $batch->status === CooperativeImportBatchStatus::Replaced
                    ? 'Penggantian data lama'
                    : 'Impor data lama',
                'label_en' => $batch->status === CooperativeImportBatchStatus::Replaced
                    ? 'Legacy data replacement'
                    : 'Legacy import',
                'member_name' => null,
                'operator_id' => $batch->created_by,
                'timestamp' => $batch->completed_at?->toIso8601String(),
                'detail' => '#'.$batch->id.' · '.$batch->status->value,
                'imported' => true,
            ]);

        $memberships = CooperativeMembership::query()
            ->with('member')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(function (CooperativeMembership $membership) {
                $isInactive = $membership->status === CooperativeMembershipStatus::Inactive;
                $timestamp = $isInactive
                    ? ($membership->ended_at ?? $membership->updated_at)
                    : ($membership->joined_at ?? $membership->created_at);

                return [
                    'action' => $isInactive ? 'membership_deactivation' : 'membership_activation',
                    'label_id' => $isInactive
                        ? 'Keanggotaan Koperasi dinonaktifkan'
                        : 'Keanggotaan Koperasi aktif',
                    'label_en' => $isInactive
                        ? 'Cooperative membership deactivated'
                        : 'Cooperative membership activated',
                    'member_name' => $membership->member?->legal_name,
                    'operator_id' => null,
                    'timestamp' => $timestamp?->toIso8601String(),
                    'detail' => $membership->status->value,
                    'imported' => false,
                ];
            });

        return $ledger
            ->concat($reviews)
            ->concat($imports)
            ->concat($memberships)
            ->sortByDesc('timestamp')
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @return array{action: string, label_id: string, label_en: string, operator_id: mixed, imported: bool}
     */
    private function ledgerActivityKind(ParticipationEntry $entry, mixed $origin): array
    {
        if ($entry->units_origin === ParticipationUnitsOrigin::ManualReconciliation) {
            return [
                'action' => 'manual_reconciliation',
                'label_id' => 'Rekonsiliasi PSN manual',
                'label_en' => 'Manual PSN reconciliation',
                'operator_id' => $entry->provenance['operator_id'] ?? $entry->provenance['resolved_by'] ?? null,
                'imported' => false,
            ];
        }

        if (
            $entry->source_type === CooperativeSourceType::LegacyImport
            && $entry->entry_type === ParticipationEntryType::OpeningBalance
        ) {
            return [
                'action' => 'legacy_opening',
                'label_id' => 'Saldo awal impor lama',
                'label_en' => 'Legacy opening (imported)',
                'operator_id' => null,
                'imported' => true,
            ];
        }

        if ($entry->source_type === CooperativeSourceType::ImportReversal) {
            return [
                'action' => 'import_reversal',
                'label_id' => 'Pembatalan impor lama',
                'label_en' => 'Legacy import reversal',
                'operator_id' => $entry->provenance['operator_id'] ?? null,
                'imported' => true,
            ];
        }

        if ($origin === 'correction' || ($entry->provenance['reason_code'] ?? null) === 'administrative_correction') {
            return [
                'action' => 'correction_adjustment',
                'label_id' => 'Koreksi saham PSN',
                'label_en' => 'PSN share correction',
                'operator_id' => $entry->provenance['operator_id'] ?? null,
                'imported' => false,
            ];
        }

        if ($entry->source_type === CooperativeSourceType::Staff) {
            return [
                'action' => 'administrative_adjustment',
                'label_id' => 'Penyesuaian saham PSN',
                'label_en' => 'Administrative PSN share adjustment',
                'operator_id' => $entry->provenance['operator_id'] ?? null,
                'imported' => false,
            ];
        }

        return [
            'action' => $entry->entry_type->value,
            'label_id' => 'Perubahan ledger PSN',
            'label_en' => 'PSN ledger change',
            'operator_id' => $entry->provenance['operator_id'] ?? null,
            'imported' => $entry->source_type === CooperativeSourceType::LegacyImport,
        ];
    }
}
