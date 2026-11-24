<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeImportRowStatus;
use App\Enums\CooperativeMatchMethod;
use App\Enums\CooperativeMemberResolution;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeImportRow;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ManualRowReviewer
{
    public function __construct(
        private readonly RowNormalizer $normalizer = new RowNormalizer,
        private readonly ImportBatchService $batches = new ImportBatchService,
    ) {}

    public function assignMember(CooperativeImportRow $row, Member $member, User $actor, ?string $reviewNotes = null): CooperativeImportRow
    {
        if ($row->isCommitted()) {
            throw new RuntimeException('A committed row cannot be rematched.');
        }

        $business = CooperativeBusiness::psn();
        $result = $this->normalizer->normalize($row->raw_data ?? [], $business);

        $errors = array_values(array_filter(
            $result['errors'],
            fn (string $code) => ! in_array($code, [
                'unmatched_member',
                'ambiguous_member',
                'name_collision',
                'source_duplicate_nik',
                'source_duplicate_identifier',
                'source_duplicate_email',
                'source_duplicate_phone',
            ], true),
        ));

        $normalized = $result['normalized'];
        $normalized['propose_create_member'] = false;
        $normalized['propose_create_membership'] = $member->cooperativeMembership === null;
        $normalized['member_resolution'] = CooperativeMemberResolution::Manual->value;
        $normalized['candidate_ids'] = [$member->id];
        $normalized['components']['member']['status'] = 'ready';
        $normalized['components']['cooperative_membership']['status'] = 'ready';
        $normalized['review_categories']['identity'] = [];

        $status = $errors === []
            ? CooperativeImportRowStatus::Ready
            : ($this->hasHardError($errors) ? CooperativeImportRowStatus::Invalid : CooperativeImportRowStatus::NeedsReview);

        $row->forceFill([
            'matched_member_id' => $member->id,
            'match_method' => CooperativeMatchMethod::Manual,
            'matched_by' => $actor->id,
            'matched_at' => now(),
            'normalized_data' => $normalized,
            'validation_status' => $status,
            'validation_errors' => $errors === [] ? null : $errors,
            'review_notes' => $reviewNotes ?? $row->review_notes,
        ])->save();

        Log::info('cooperative import row matched manually', [
            'row_id' => $row->id,
            'batch_id' => $row->batch_id,
            'selected_member_id' => $member->id,
            'operator_id' => $actor->id,
            'has_notes' => filled($reviewNotes),
        ]);

        $this->batches->refreshBatchStatus($row->batch);

        return $row->fresh() ?? $row;
    }

    public function addNotes(CooperativeImportRow $row, string $notes): CooperativeImportRow
    {
        $row->forceFill(['review_notes' => $notes])->save();

        return $row;
    }

    /**
     * @param  list<string>  $errors
     */
    private function hasHardError(array $errors): bool
    {
        foreach ($errors as $error) {
            if (str_starts_with($error, 'malformed_') || $error === 'empty_row' || $error === 'missing_name') {
                return true;
            }
        }

        return false;
    }
}
