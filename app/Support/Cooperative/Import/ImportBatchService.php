<?php

namespace App\Support\Cooperative\Import;

use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportRowStatus;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class ImportBatchService
{
    public function __construct(
        private readonly SpreadsheetReader $reader = new SpreadsheetReader,
        private readonly HeaderMapper $headers = new HeaderMapper,
        private readonly RowNormalizer $normalizer = new RowNormalizer,
        private readonly SourceDuplicateAuditor $duplicates = new SourceDuplicateAuditor,
    ) {}

    public function upload(UploadedFile $file, User $actor): CooperativeImportBatch
    {
        $original = $this->safeOriginalName($file);
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw ImportException::unreadableFile();
        }

        $disk = (string) config('cooperative.imports_disk', 'cooperative_imports');
        $storedName = Str::uuid()->toString().'.'.$extension;
        $storedPath = $storedName;

        $stream = fopen($file->getRealPath(), 'r');
        if ($stream === false) {
            throw ImportException::unreadableFile();
        }

        try {
            Storage::disk($disk)->writeStream($storedPath, $stream);
        } finally {
            fclose($stream);
        }

        $absolute = Storage::disk($disk)->path($storedPath);
        $hash = hash_file('sha256', $absolute);

        $batch = CooperativeImportBatch::query()->create([
            'original_filename' => $original,
            'stored_path' => $storedPath,
            'file_hash' => $hash,
            'status' => CooperativeImportBatchStatus::Uploaded,
            'created_by' => $actor->id,
            'started_at' => now(),
        ]);

        try {
            $sheets = $this->reader->inspect($absolute, $original);
            $batch->forceFill([
                'worksheets_json' => $sheets,
            ])->save();

            $this->maybeAutoParse($batch, $sheets);
        } catch (Throwable $exception) {
            $this->failBatch($batch, $exception, $hash);

            return $batch->fresh() ?? $batch;
        }

        Log::info('cooperative import uploaded', [
            'batch_id' => $batch->id,
            'file_hash' => $hash,
            'worksheet_name' => $batch->worksheet_name,
            'row_count' => $batch->rows()->count(),
        ]);

        return $batch->fresh(['rows']) ?? $batch;
    }

    /**
     * @param  list<array<string, mixed>>  $sheets
     */
    private function maybeAutoParse(CooperativeImportBatch $batch, array $sheets): void
    {
        $visibleCandidates = array_values(array_filter(
            $sheets,
            fn (array $sheet): bool => ($sheet['candidate'] ?? false) === true
                && ($sheet['visible'] ?? false) === true,
        ));

        if (count($visibleCandidates) === 1) {
            $this->bindWorksheet($batch, $visibleCandidates[0]);
            $this->parse($batch);

            return;
        }

        if ($visibleCandidates === [] && ! $this->hasCandidate($sheets)) {
            throw ImportException::noCompatibleWorksheet();
        }

        $batch->forceFill([
            'status' => CooperativeImportBatchStatus::Uploaded,
            'summary_json' => [
                'needs_sheet_selection' => true,
                'error' => null,
            ],
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    public function selectWorksheet(CooperativeImportBatch $batch, string $worksheetName): CooperativeImportBatch
    {
        if ($batch->isCommitted() || $batch->isReversed()) {
            return $batch;
        }

        if (! in_array($batch->status, [
            CooperativeImportBatchStatus::Uploaded,
            CooperativeImportBatchStatus::Failed,
        ], true)) {
            throw new \RuntimeException('This import batch already has a selected worksheet.');
        }

        $sheets = $batch->worksheets_json ?? [];
        $selected = null;

        foreach ($sheets as $sheet) {
            if (($sheet['name'] ?? null) === $worksheetName) {
                $selected = $sheet;
                break;
            }
        }

        if ($selected === null) {
            throw ImportException::worksheetNotFound();
        }

        try {
            $this->bindWorksheet($batch, $selected);
            $this->parse($batch);
        } catch (Throwable $exception) {
            $this->failBatch($batch, $exception, $batch->file_hash);

            return $batch->fresh() ?? $batch;
        }

        return $batch->fresh(['rows']) ?? $batch;
    }

    public function parse(CooperativeImportBatch $batch): CooperativeImportBatch
    {
        if ($batch->isCommitted() || $batch->isReversed()) {
            return $batch;
        }

        if ($batch->worksheet_name === null || $batch->worksheet_name === '') {
            throw ImportException::worksheetNotFound();
        }

        $disk = (string) config('cooperative.imports_disk', 'cooperative_imports');
        $absolute = Storage::disk($disk)->path($batch->stored_path);
        $business = CooperativeBusiness::psn();
        $columnMap = null;
        $headerLabels = null;

        if ($batch->rows()->exists()) {
            $batch->rows()->whereNull('committed_at')->delete();
        }

        $stats = $this->reader->processSheet(
            $absolute,
            $batch->original_filename,
            $batch->worksheet_name,
            function (array $headers, array $row) use (&$columnMap, &$headerLabels, $batch, $business): void {
                $headerLabels ??= $headers;
                $columnMap ??= $this->mappedHeaders($headers);
                $raw = $this->reader->rawFromCells($headers, $columnMap, $row['cells']);
                $result = $this->normalizer->normalize($raw, $business);

                CooperativeImportRow::query()->create([
                    'batch_id' => $batch->id,
                    'source_row_number' => $row['source_row_number'],
                    'raw_data' => $raw,
                    'source_fingerprint' => $result['source_fingerprint'],
                    'normalized_data' => $result['normalized'],
                    'matched_member_id' => $result['matched_member_id'],
                    'match_method' => $result['match_method'],
                    'validation_status' => $result['status'],
                    'validation_errors' => $result['errors'] === [] ? null : $result['errors'],
                ]);
            },
        );

        $batch->forceFill([
            'header_row_number' => $stats['header_row'],
            'worksheet_visible' => $stats['visible'],
        ])->save();

        if ($stats['member_rows'] === 0) {
            throw ImportException::noMemberRows();
        }

        $duplicateRows = $this->duplicates->apply($batch->rows()->get());

        $this->refreshBatchStatus($batch, [
            'ignored_footer_or_summary_rows' => $stats['ignored_footer_or_summary_rows'],
            'source_duplicate_conflict_rows' => $duplicateRows,
        ]);

        return $batch->fresh(['rows']) ?? $batch;
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    private function bindWorksheet(CooperativeImportBatch $batch, array $sheet): void
    {
        $batch->forceFill([
            'worksheet_name' => $sheet['name'],
            'worksheet_visible' => $sheet['visible'] ?? null,
            'header_row_number' => $sheet['header_row'] ?? null,
        ]);

        $this->applyDuplicatePointer($batch);
        $batch->save();
    }

    private function applyDuplicatePointer(CooperativeImportBatch $batch): void
    {
        if ($batch->worksheet_name === null || $batch->worksheet_name === '') {
            $batch->duplicate_of_batch_id = null;

            return;
        }

        $duplicate = CooperativeImportBatch::query()
            ->where('file_hash', $batch->file_hash)
            ->where('worksheet_name', $batch->worksheet_name)
            ->where('id', '!=', $batch->id)
            ->orderByDesc('id')
            ->first();

        $batch->duplicate_of_batch_id = $duplicate?->id;
    }

    /**
     * @param  list<array<string, mixed>>  $sheets
     */
    private function hasCandidate(array $sheets): bool
    {
        foreach ($sheets as $sheet) {
            if (($sheet['candidate'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    private function mappedHeaders(array $headers): array
    {
        $map = $this->headers->map($headers);
        $this->headers->assertCoreHeaders($map);

        return $map;
    }

    public function refreshBatchStatus(CooperativeImportBatch $batch, array $extraSummary = []): void
    {
        $batch = $batch->fresh() ?? $batch;

        if ($batch->isReversed() || $batch->isReplaced()) {
            if ($extraSummary !== []) {
                $batch->forceFill([
                    'summary_json' => [...($batch->summary_json ?? []), ...$extraSummary],
                ])->save();
            }

            return;
        }

        $counts = $this->statusCounts($batch);
        $summary = [
            ...$this->previewSummary($batch, $counts),
            ...$extraSummary,
        ];

        $hasPartial = $batch->rows()->get()->contains(fn (CooperativeImportRow $row) => $row->hasCommittedComponents());
        $hasReady = $batch->rows()->get()->contains(fn (CooperativeImportRow $row) => $row->hasReadyUncommittedComponents());

        // After a successful commit pass with leftover review rows, keep the batch
        // open for later component resolution while recording terminal-with-review.
        if ($batch->status === CooperativeImportBatchStatus::Committed && ! $hasReady) {
            $batch->forceFill(['summary_json' => $summary])->save();

            return;
        }

        $status = CooperativeImportBatchStatus::Parsed;

        if ((($counts['committed'] ?? 0) > 0 || $hasPartial) && ! $hasReady) {
            $status = (($counts['needs_review'] ?? 0) > 0 || ($counts['invalid'] ?? 0) > 0)
                ? CooperativeImportBatchStatus::CompletedWithReview
                : CooperativeImportBatchStatus::Committed;
        } elseif (($counts['invalid'] ?? 0) > 0 && ! $hasReady && ($counts['needs_review'] ?? 0) === 0 && ($counts['committed'] ?? 0) === 0 && ! $hasPartial) {
            $status = CooperativeImportBatchStatus::Failed;
        } elseif (($counts['needs_review'] ?? 0) > 0 || ($counts['invalid'] ?? 0) > 0 || $hasPartial) {
            $status = CooperativeImportBatchStatus::ReviewRequired;
        } elseif ($hasReady || ($counts['ready'] ?? 0) > 0) {
            $status = CooperativeImportBatchStatus::Ready;
        }

        $batch->forceFill([
            'status' => $status,
            'summary_json' => $summary,
            'completed_at' => $status->isTerminalSuccess() ? ($batch->completed_at ?? now()) : $batch->completed_at,
        ])->save();
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(CooperativeImportBatch $batch): array
    {
        $rows = $batch->rows()->get(['validation_status', 'matched_member_id', 'normalized_data']);

        return [
            'total' => $rows->count(),
            'ready' => $rows->where('validation_status', CooperativeImportRowStatus::Ready)->count(),
            'needs_review' => $rows->where('validation_status', CooperativeImportRowStatus::NeedsReview)->count(),
            'invalid' => $rows->where('validation_status', CooperativeImportRowStatus::Invalid)->count(),
            'committed' => $rows->where('validation_status', CooperativeImportRowStatus::Committed)->count(),
            'matched' => $rows->filter(fn (CooperativeImportRow $row) => $row->matched_member_id !== null)->count(),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    public function previewSummary(CooperativeImportBatch $batch, ?array $counts = null): array
    {
        $counts ??= $this->statusCounts($batch);
        $allRows = $batch->rows()->get();

        $proposedMemberships = 0;
        $proposedMembers = 0;
        $existingMatched = 0;
        $identityReviewRows = 0;
        $financialReviewRows = 0;
        $dateReviewRows = 0;
        $shareReviewRows = 0;
        $profileWarningRows = 0;
        $invalidIdentityRows = 0;
        $memberReady = 0;
        $membershipReady = 0;
        $fundsReady = 0;
        $psnReady = 0;
        $psnReview = 0;
        $proposedPrincipalAmount = 0;
        $proposedVoluntaryAmount = 0;
        $proposedParticipants = 0;
        $proposedUnits = 0;
        $explicitUnits = 0;
        $inferredUnits = 0;
        $explicitUnitRows = 0;
        $inferredUnitRows = 0;

        foreach ($allRows as $row) {
            $data = $row->normalized_data ?? [];
            $resolution = (string) ($data['member_resolution'] ?? '');
            $categories = $data['review_categories'] ?? [];
            $components = $data['components'] ?? [];

            if ($resolution === 'proposed_legacy_create' || ($data['propose_create_member'] ?? false) === true) {
                $proposedMembers++;
            }

            if ($resolution === 'existing' || $resolution === 'manual') {
                $existingMatched++;
            }

            if ($resolution === 'invalid' || $resolution === 'ambiguous') {
                $invalidIdentityRows++;
            }

            if (($categories['identity'] ?? []) !== []) {
                $identityReviewRows++;
            }

            if (($categories['financial'] ?? []) !== []) {
                $financialReviewRows++;
            }

            if (($categories['date'] ?? []) !== []) {
                $dateReviewRows++;
            }

            if (($categories['share'] ?? []) !== []) {
                $shareReviewRows++;
            }

            if (($categories['profile'] ?? $data['profile_warnings'] ?? []) !== []) {
                $profileWarningRows++;
            }

            if (($components['member']['status'] ?? null) === 'ready') {
                $memberReady++;
            }
            if (($components['cooperative_membership']['status'] ?? null) === 'ready') {
                $membershipReady++;
            }
            if (($components['funds']['status'] ?? null) === 'ready') {
                $fundsReady++;
                $proposedPrincipalAmount += max(0, (int) ($data['principal_amount'] ?? 0));
                $proposedVoluntaryAmount += max(0, (int) ($data['voluntary_amount'] ?? 0));
            }
            if (($components['psn']['status'] ?? null) === 'ready') {
                $psnReady++;
                $units = (int) ($data['units'] ?? 0);
                if ($units > 0) {
                    $proposedParticipants++;
                    $proposedUnits += $units;
                    if (($data['units_origin'] ?? null) === 'explicit') {
                        $explicitUnits += $units;
                        $explicitUnitRows++;
                    }
                    if (($data['units_origin'] ?? null) === 'inferred') {
                        $inferredUnits += $units;
                        $inferredUnitRows++;
                    }
                }
            }
            if (($components['psn']['status'] ?? null) === 'needs_review') {
                $psnReview++;
            }

            if (($data['propose_create_membership'] ?? false) === true
                && ($components['cooperative_membership']['status'] ?? null) !== 'needs_review'
                && $row->validation_status !== CooperativeImportRowStatus::Invalid) {
                $proposedMemberships++;
            }
        }

        return [
            ...$counts,
            'duplicate_file' => $batch->duplicate_of_batch_id !== null,
            'worksheet_name' => $batch->worksheet_name,
            'worksheet_visible' => $batch->worksheet_visible,
            'header_row_number' => $batch->header_row_number,
            'needs_sheet_selection' => $batch->worksheet_name === null || $batch->worksheet_name === '',
            'existing_members_matched' => $existingMatched,
            'proposed_legacy_members' => $proposedMembers,
            'identity_conflict_rows' => $identityReviewRows,
            'identity_review_rows' => $identityReviewRows,
            'invalid_identity_rows' => $invalidIdentityRows,
            'profile_warning_rows' => $profileWarningRows,
            'financial_review_rows' => $financialReviewRows,
            'date_review_rows' => $dateReviewRows,
            'share_review_rows' => $shareReviewRows,
            'member_ready_rows' => $memberReady,
            'cooperative_membership_ready_rows' => $membershipReady,
            'funds_ready_rows' => $fundsReady,
            'psn_ready_rows' => $psnReady,
            'psn_review_rows' => $psnReview,
            'proposed_cooperative_memberships' => $proposedMemberships,
            'proposed_principal_amount' => $proposedPrincipalAmount,
            'proposed_voluntary_amount' => $proposedVoluntaryAmount,
            'proposed_principal_balances' => $fundsReady,
            'proposed_voluntary_balances' => $allRows->filter(fn ($row) => (($row->normalized_data['components']['funds']['status'] ?? null) === 'ready') && (int) (($row->normalized_data['voluntary_amount'] ?? 0)) > 0)->count(),
            'proposed_psn_participants' => $proposedParticipants,
            'proposed_total_psn_units' => $proposedUnits,
            'proposed_explicit_units' => $explicitUnits,
            'proposed_inferred_units' => $inferredUnits,
            'explicit_unit_rows' => $explicitUnitRows,
            'inferred_unit_rows' => $inferredUnitRows,
            'ignored_footer_or_summary_rows' => (int) ($batch->summary_json['ignored_footer_or_summary_rows'] ?? 0),
            'source_duplicate_conflict_rows' => (int) ($batch->summary_json['source_duplicate_conflict_rows'] ?? 0),
            'creates_user_accounts' => false,
            'creates_membership_applications' => false,
            'legacy_member_bootstrap' => true,
        ];
    }

    private function failBatch(CooperativeImportBatch $batch, Throwable $exception, string $hash): void
    {
        $message = $exception instanceof ImportException
            ? $exception->getMessage()
            : ImportException::unreadableFile()->getMessage();

        $batch->forceFill([
            'status' => CooperativeImportBatchStatus::Failed,
            'summary_json' => [
                'error' => $message,
                'needs_sheet_selection' => false,
            ],
        ])->save();

        Log::notice('cooperative import parse failed', [
            'batch_id' => $batch->id,
            'file_hash' => $hash,
        ]);
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename(str_replace(['\\', '/'], '', $file->getClientOriginalName()));

        return $name === '' ? 'import.csv' : $name;
    }
}
