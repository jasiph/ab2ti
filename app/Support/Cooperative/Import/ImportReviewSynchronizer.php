<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewStatus;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeImportRow;
use RuntimeException;

final class ImportReviewSynchronizer
{
    /**
     * Materialize review items from import row warnings. Idempotent.
     *
     * Also removes obsolete duplicate reconciliation items (e.g. PSN share
     * codes previously mis-classified as other_legacy) when they have no
     * operator audit actions.
     */
    public function sync(CooperativeImportBatch $batch): void
    {
        $batch->rows()->orderBy('source_row_number')->each(function (CooperativeImportRow $row): void {
            foreach ($this->detectItems($row) as $item) {
                CooperativeImportReviewItem::query()->firstOrCreate(
                    [
                        'batch_id' => $row->batch_id,
                        'import_row_id' => $row->id,
                        'category' => $item['category']->value,
                        'warning_code' => $item['warning_code'],
                    ],
                    [
                        'status' => CooperativeImportReviewStatus::Pending,
                    ],
                );
            }
        });

        $this->pruneMisclassifiedPsnOtherLegacy($batch);
    }

    /**
     * @return array{
     *     removed: int,
     *     blocked_with_audit: int,
     *     blocked_item_ids: list<int>
     * }
     */
    public function pruneMisclassifiedPsnOtherLegacy(CooperativeImportBatch $batch): array
    {
        $psnCodes = CooperativeImportReviewCategory::psnShareCodes();
        $candidates = CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->where('category', CooperativeImportReviewCategory::OtherLegacy)
            ->whereIn('warning_code', $psnCodes)
            ->with('actions')
            ->get();

        $removed = 0;
        $blocked = [];

        foreach ($candidates as $item) {
            if ($item->actions->isNotEmpty() || $item->status->isTerminal()) {
                $blocked[] = $item->id;

                continue;
            }

            $item->delete();
            $removed++;
        }

        if ($blocked !== []) {
            throw new RuntimeException(
                'Cannot prune misclassified other_legacy PSN review items that already have audit actions or terminal status: '
                .implode(',', $blocked)
            );
        }

        return [
            'removed' => $removed,
            'blocked_with_audit' => 0,
            'blocked_item_ids' => [],
        ];
    }

    /**
     * Canonical category for a warning code used by reconciliation items.
     */
    public static function canonicalCategoryForWarning(string $warningCode): ?CooperativeImportReviewCategory
    {
        if (in_array($warningCode, CooperativeImportReviewCategory::profileWarningCodes(), true)) {
            return CooperativeImportReviewCategory::ProfileIncomplete;
        }

        if (in_array($warningCode, ['shared_email', 'source_duplicate_email'], true)) {
            return CooperativeImportReviewCategory::SharedEmail;
        }

        if (in_array($warningCode, ['shared_phone', 'source_duplicate_phone'], true)) {
            return CooperativeImportReviewCategory::SharedPhone;
        }

        if (in_array($warningCode, CooperativeImportReviewCategory::duplicateNikCodes(), true)) {
            return CooperativeImportReviewCategory::DuplicateNik;
        }

        if (in_array($warningCode, CooperativeImportReviewCategory::duplicatePersonCodes(), true)) {
            return CooperativeImportReviewCategory::DuplicatePerson;
        }

        if (in_array($warningCode, CooperativeImportReviewCategory::legacyIdentityCodes(), true)) {
            return CooperativeImportReviewCategory::LegacyIdentity;
        }

        if (in_array($warningCode, CooperativeImportReviewCategory::psnShareCodes(), true)) {
            return CooperativeImportReviewCategory::PsnUnconfirmed;
        }

        if (in_array($warningCode, CooperativeImportReviewCategory::otherLegacyCodes(), true)) {
            return CooperativeImportReviewCategory::OtherLegacy;
        }

        return null;
    }

    /**
     * @return list<array{category: CooperativeImportReviewCategory, warning_code: string}>
     */
    private function detectItems(CooperativeImportRow $row): array
    {
        $data = $row->normalized_data ?? [];
        $errors = $row->validation_errors ?? [];
        $profileWarnings = $data['profile_warnings'] ?? [];
        $categories = $data['review_categories'] ?? [];
        $items = [];
        $seen = [];

        $add = function (CooperativeImportReviewCategory $category, string $code) use (&$items, &$seen): void {
            $canonical = self::canonicalCategoryForWarning($code) ?? $category;
            $key = $canonical->value.'|'.$code;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $items[] = ['category' => $canonical, 'warning_code' => $code];
        };

        foreach (CooperativeImportReviewCategory::profileWarningCodes() as $code) {
            if (in_array($code, $profileWarnings, true) || in_array($code, $errors, true)) {
                $add(CooperativeImportReviewCategory::ProfileIncomplete, $code);
            }
        }

        if (in_array('shared_email', $errors, true) || in_array('source_duplicate_email', $errors, true)) {
            $add(
                CooperativeImportReviewCategory::SharedEmail,
                in_array('shared_email', $errors, true) ? 'shared_email' : 'source_duplicate_email',
            );
        }

        if (in_array('shared_phone', $errors, true) || in_array('source_duplicate_phone', $errors, true)) {
            $add(
                CooperativeImportReviewCategory::SharedPhone,
                in_array('shared_phone', $errors, true) ? 'shared_phone' : 'source_duplicate_phone',
            );
        }

        foreach (CooperativeImportReviewCategory::duplicateNikCodes() as $code) {
            if (in_array($code, $errors, true)) {
                $add(CooperativeImportReviewCategory::DuplicateNik, $code);
            }
        }

        foreach (CooperativeImportReviewCategory::duplicatePersonCodes() as $code) {
            if (in_array($code, $errors, true)) {
                $add(CooperativeImportReviewCategory::DuplicatePerson, $code);
            }
        }

        foreach ($categories['identity'] ?? [] as $code) {
            if (in_array($code, CooperativeImportReviewCategory::legacyIdentityCodes(), true)) {
                $add(CooperativeImportReviewCategory::LegacyIdentity, $code);
            }
        }

        foreach (CooperativeImportReviewCategory::legacyIdentityCodes() as $code) {
            if (in_array($code, $errors, true)) {
                $add(CooperativeImportReviewCategory::LegacyIdentity, $code);
            }
        }

        $psnNeedsReview = ($data['components']['psn']['status'] ?? null) === 'needs_review';
        if ($psnNeedsReview) {
            $shareCodes = array_values(array_unique([
                ...($categories['share'] ?? []),
                ...array_intersect($errors, CooperativeImportReviewCategory::psnShareCodes()),
            ]));

            if ($shareCodes === []) {
                $shareCodes = ['non_integral_legacy_total'];
            }

            foreach ($shareCodes as $code) {
                $add(CooperativeImportReviewCategory::PsnUnconfirmed, $code);
            }
        }

        foreach (CooperativeImportReviewCategory::otherLegacyCodes() as $code) {
            if (in_array($code, $errors, true)
                || in_array($code, $categories['financial'] ?? [], true)
                || in_array($code, $categories['date'] ?? [], true)) {
                $add(CooperativeImportReviewCategory::OtherLegacy, $code);
            }
        }

        return $items;
    }
}
