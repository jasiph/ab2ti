<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewStatus;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportReviewItem;

final class ImportReviewInventory
{
    public function __construct(
        private readonly ImportReviewSynchronizer $synchronizer = new ImportReviewSynchronizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(CooperativeImportBatch $batch): array
    {
        $this->synchronizer->sync($batch);

        $items = CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->get();

        $categoryCounts = [];
        foreach (CooperativeImportReviewCategory::cases() as $category) {
            $categoryCounts[$category->value] = $items
                ->where('category', $category)
                ->count();
        }

        $uniqueRowIds = $items->pluck('import_row_id')->unique();
        $resolvedRowIds = $items
            ->filter(fn (CooperativeImportReviewItem $item) => $item->status->isTerminal())
            ->pluck('import_row_id')
            ->unique();

        $pendingItems = $items->filter(fn (CooperativeImportReviewItem $item) => $item->status === CooperativeImportReviewStatus::Pending);
        $uniquePendingRowIds = $pendingItems->pluck('import_row_id')->unique();

        $profileBreakdown = $items
            ->where('category', CooperativeImportReviewCategory::ProfileIncomplete)
            ->groupBy('warning_code')
            ->map->count()
            ->all();

        $otherBreakdown = $items
            ->where('category', CooperativeImportReviewCategory::OtherLegacy)
            ->groupBy('warning_code')
            ->map->count()
            ->all();

        return [
            'total_review_flags' => $items->count(),
            'unique_rows_with_review' => $uniqueRowIds->count(),
            'resolved_flags' => $items->filter(fn ($item) => $item->status->isTerminal())->count(),
            'pending_flags' => $pendingItems->count(),
            'unique_rows_resolved' => $resolvedRowIds->count(),
            'unique_rows_remaining' => $uniquePendingRowIds->count(),
            'category_counts' => $categoryCounts,
            'profile_breakdown' => $profileBreakdown,
            'other_breakdown' => $otherBreakdown,
            'import_status' => $batch->status->value,
            'reconciliation_complete' => $pendingItems->isEmpty(),
        ];
    }
}
