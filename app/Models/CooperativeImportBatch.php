<?php

namespace App\Models;

use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CooperativeImportBatch extends Model
{
    /** @use HasFactory<\Database\Factories\CooperativeImportBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'stored_path',
        'file_hash',
        'worksheet_name',
        'worksheet_visible',
        'header_row_number',
        'status',
        'import_mode',
        'created_by',
        'started_at',
        'completed_at',
        'reversed_at',
        'reversed_by',
        'duplicate_of_batch_id',
        'replaces_batch_id',
        'replaced_by_batch_id',
        'replace_acknowledged_at',
        'replace_acknowledged_by',
        'summary_json',
        'worksheets_json',
    ];

    protected function casts(): array
    {
        return [
            'status' => CooperativeImportBatchStatus::class,
            'import_mode' => CooperativeImportMode::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'reversed_at' => 'datetime',
            'replace_acknowledged_at' => 'datetime',
            'summary_json' => 'array',
            'worksheets_json' => 'array',
            'worksheet_visible' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function replaceAcknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replace_acknowledged_by');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_batch_id');
    }

    public function replacesBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_batch_id');
    }

    public function replacedByBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_batch_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CooperativeImportRow::class, 'batch_id');
    }

    public function isCommitted(): bool
    {
        return $this->status === CooperativeImportBatchStatus::Committed
            || $this->status === CooperativeImportBatchStatus::CompletedWithReview;
    }

    public function isReversed(): bool
    {
        return $this->status === CooperativeImportBatchStatus::Reversed;
    }

    public function isReplaced(): bool
    {
        return $this->status === CooperativeImportBatchStatus::Replaced;
    }

    public function canCommit(?CooperativeImportMode $mode = null): bool
    {
        if ($this->isReversed() || $this->isReplaced()) {
            return false;
        }

        if ($this->status === CooperativeImportBatchStatus::Failed
            || $this->status === CooperativeImportBatchStatus::Uploaded) {
            return false;
        }

        if ($this->status === CooperativeImportBatchStatus::Committed) {
            return $this->rows()
                ->get()
                ->contains(fn (CooperativeImportRow $row) => $row->hasReadyUncommittedComponents());
        }

        if (! in_array($this->status, [
            CooperativeImportBatchStatus::Parsed,
            CooperativeImportBatchStatus::ReviewRequired,
            CooperativeImportBatchStatus::Ready,
            CooperativeImportBatchStatus::CompletedWithReview,
        ], true)) {
            return false;
        }

        $mode ??= CooperativeImportMode::Append;

        if ($mode === CooperativeImportMode::ReplaceLegacy) {
            return $this->rows()
                ->where('validation_status', '!=', \App\Enums\CooperativeImportRowStatus::Invalid)
                ->get()
                ->contains(function (CooperativeImportRow $row): bool {
                    $name = trim((string) ($row->normalized_data['name'] ?? ''));

                    return $name !== '';
                });
        }

        return $this->rows()
            ->get()
            ->contains(fn (CooperativeImportRow $row) => $row->hasReadyUncommittedComponents());
    }

    public function canReverse(): bool
    {
        if ($this->isReversed() || $this->isReplaced()) {
            return false;
        }

        return $this->isCommitted()
            || $this->rows()->get()->contains(fn (CooperativeImportRow $row) => $row->hasCommittedComponents());
    }

    public function needsSheetSelection(): bool
    {
        if ($this->isCommitted() || $this->isReversed() || $this->isReplaced()) {
            return false;
        }

        if (! in_array($this->status, [
            CooperativeImportBatchStatus::Uploaded,
            CooperativeImportBatchStatus::Failed,
        ], true)) {
            return false;
        }

        return is_array($this->worksheets_json) && $this->worksheets_json !== [];
    }
}
