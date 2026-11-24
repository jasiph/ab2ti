<?php

namespace App\Models;

use App\Enums\CooperativeFundEntryType;
use App\Enums\CooperativeFundType;
use App\Enums\CooperativeSourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CooperativeFundEntry extends Model
{
    /** @use HasFactory<\Database\Factories\CooperativeFundEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'cooperative_membership_id',
        'fund_type',
        'entry_type',
        'amount',
        'effective_date_start',
        'effective_date_end',
        'source_type',
        'import_batch_id',
        'legacy_source_row_id',
        'reversed_at',
        'reversed_by',
        'notes',
        'provenance',
    ];

    protected function casts(): array
    {
        return [
            'fund_type' => CooperativeFundType::class,
            'entry_type' => CooperativeFundEntryType::class,
            'amount' => 'integer',
            'effective_date_start' => 'date',
            'effective_date_end' => 'date',
            'source_type' => CooperativeSourceType::class,
            'reversed_at' => 'datetime',
            'provenance' => 'array',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(CooperativeMembership::class, 'cooperative_membership_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportBatch::class, 'import_batch_id');
    }

    public function legacySourceRow(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportRow::class, 'legacy_source_row_id');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function scopeNotReversed(Builder $query): Builder
    {
        return $query->whereNull('reversed_at');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
