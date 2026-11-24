<?php

namespace App\Models;

use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CooperativeImportReviewAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'review_item_id',
        'batch_id',
        'import_row_id',
        'category',
        'warning_code',
        'action',
        'outcome',
        'operator_id',
        'administrative_note',
        'audit_metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => CooperativeImportReviewCategory::class,
            'outcome' => CooperativeImportReviewOutcome::class,
            'audit_metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function reviewItem(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportReviewItem::class, 'review_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportBatch::class, 'batch_id');
    }

    public function importRow(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportRow::class, 'import_row_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
