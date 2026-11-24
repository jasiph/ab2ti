<?php

namespace App\Models;

use App\Enums\CooperativeSourceType;
use App\Enums\ParticipationEntryType;
use App\Enums\ParticipationUnitsOrigin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipationEntry extends Model
{
    /** @use HasFactory<\Database\Factories\ParticipationEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'business_participation_id',
        'entry_type',
        'units',
        'unit_value',
        'amount',
        'effective_date_start',
        'effective_date_end',
        'share_reference',
        'units_origin',
        'source_type',
        'import_batch_id',
        'legacy_source_row_id',
        'reverses_entry_id',
        'notes',
        'provenance',
        'confirmation_token',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => ParticipationEntryType::class,
            'units' => 'integer',
            'unit_value' => 'integer',
            'amount' => 'integer',
            'effective_date_start' => 'date',
            'effective_date_end' => 'date',
            'units_origin' => ParticipationUnitsOrigin::class,
            'source_type' => CooperativeSourceType::class,
            'provenance' => 'array',
        ];
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(BusinessParticipation::class, 'business_participation_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportBatch::class, 'import_batch_id');
    }

    public function legacySourceRow(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportRow::class, 'legacy_source_row_id');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }
}
