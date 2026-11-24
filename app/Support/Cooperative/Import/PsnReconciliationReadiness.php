<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewOutcome;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeImportRow;

final class PsnReconciliationReadiness
{
    public function __construct(
        private readonly ImportReviewSynchronizer $synchronizer = new ImportReviewSynchronizer,
    ) {}

    /**
     * @return array{
     *     complete: bool,
     *     total_psn_source_rows: int,
     *     resolved_participation_rows: int,
     *     confirmed_no_participation_rows: int,
     *     pending_rows: int,
     *     ready_for_publication: bool
     * }
     */
    public function assess(CooperativeImportBatch $batch): array
    {
        $this->synchronizer->sync($batch);

        $psnRows = $batch->rows()
            ->get()
            ->filter(function (CooperativeImportRow $row): bool {
                $data = $row->normalized_data ?? [];

                return ($data['components']['psn']['status'] ?? null) === 'needs_review'
                    || $row->psn_committed_at !== null
                    || ! empty(array_intersect(
                        $row->validation_errors ?? [],
                        CooperativeImportReviewCategory::psnShareCodes(),
                    ));
            });

        $total = $batch->rows()->get()->filter(function (CooperativeImportRow $row): bool {
            $data = $row->normalized_data ?? [];
            $components = $data['components']['psn']['status'] ?? null;

            return in_array($components, ['ready', 'needs_review', 'committed'], true)
                || $row->psn_committed_at !== null;
        })->count();

        $resolvedParticipation = $batch->rows()->whereNotNull('psn_committed_at')->count();
        $pendingPsnReview = $batch->rows()
            ->get()
            ->filter(fn (CooperativeImportRow $row) => ($row->normalized_data['components']['psn']['status'] ?? null) === 'needs_review'
                && $row->psn_committed_at === null)
            ->count();

        $confirmedNoParticipation = CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->where('category', CooperativeImportReviewCategory::PsnUnconfirmed)
            ->where('outcome', CooperativeImportReviewOutcome::ConfirmedNoPsnParticipation)
            ->distinct('import_row_id')
            ->count('import_row_id');

        $complete = $pendingPsnReview === 0;

        return [
            'complete' => $complete,
            'total_psn_source_rows' => $total,
            'resolved_participation_rows' => $resolvedParticipation,
            'confirmed_no_participation_rows' => $confirmedNoParticipation,
            'pending_rows' => $pendingPsnReview,
            'ready_for_publication' => $complete,
        ];
    }
}
