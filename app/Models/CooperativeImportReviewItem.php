<?php

namespace App\Models;

use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewOutcome;
use App\Enums\CooperativeImportReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CooperativeImportReviewItem extends Model
{
    protected $fillable = [
        'batch_id',
        'import_row_id',
        'category',
        'warning_code',
        'status',
        'outcome',
        'resolved_by',
        'resolved_at',
        'administrative_note',
        'audit_metadata',
    ];

    protected function casts(): array
    {
        return [
            'category' => CooperativeImportReviewCategory::class,
            'status' => CooperativeImportReviewStatus::class,
            'outcome' => CooperativeImportReviewOutcome::class,
            'resolved_at' => 'datetime',
            'audit_metadata' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportBatch::class, 'batch_id');
    }

    public function importRow(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportRow::class, 'import_row_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CooperativeImportReviewAction::class, 'review_item_id');
    }

    public function isPending(): bool
    {
        return $this->status === CooperativeImportReviewStatus::Pending;
    }
}
