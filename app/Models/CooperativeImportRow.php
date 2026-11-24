<?php

namespace App\Models;

use App\Casts\EncryptedJsonCast;
use App\Enums\CooperativeImportRowStatus;
use App\Enums\CooperativeMatchMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CooperativeImportRow extends Model
{
    /** @use HasFactory<\Database\Factories\CooperativeImportRowFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'source_row_number',
        'raw_data',
        'source_fingerprint',
        'normalized_data',
        'matched_member_id',
        'created_member_id',
        'match_method',
        'matched_by',
        'matched_at',
        'validation_status',
        'validation_errors',
        'review_notes',
        'committed_at',
        'member_committed_at',
        'membership_committed_at',
        'funds_committed_at',
        'psn_committed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => EncryptedJsonCast::class,
            'normalized_data' => EncryptedJsonCast::class,
            'match_method' => CooperativeMatchMethod::class,
            'matched_at' => 'datetime',
            'validation_status' => CooperativeImportRowStatus::class,
            'validation_errors' => 'array',
            'committed_at' => 'datetime',
            'member_committed_at' => 'datetime',
            'membership_committed_at' => 'datetime',
            'funds_committed_at' => 'datetime',
            'psn_committed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CooperativeImportBatch::class, 'batch_id');
    }

    public function matchedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'matched_member_id');
    }

    public function createdMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_member_id');
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function reviewItems(): HasMany
    {
        return $this->hasMany(CooperativeImportReviewItem::class, 'import_row_id');
    }

    public function isCommitted(): bool
    {
        return $this->committed_at !== null
            || $this->validation_status === CooperativeImportRowStatus::Committed;
    }

    public function isFullyCommitted(): bool
    {
        return $this->isCommitted();
    }

    public function isReady(): bool
    {
        return $this->hasReadyUncommittedComponents();
    }

    public function hasReadyUncommittedComponents(): bool
    {
        if ($this->isFullyCommitted() || $this->validation_status === CooperativeImportRowStatus::Invalid) {
            return false;
        }

        $components = $this->normalized_data['components'] ?? [];
        $map = [
            'member' => $this->member_committed_at,
            'cooperative_membership' => $this->membership_committed_at,
            'funds' => $this->funds_committed_at,
            'psn' => $this->psn_committed_at,
        ];

        foreach ($map as $key => $committedAt) {
            if (($components[$key]['status'] ?? null) === 'ready' && $committedAt === null) {
                return true;
            }
        }

        return false;
    }

    public function hasCommittedComponents(): bool
    {
        return $this->member_committed_at !== null
            || $this->membership_committed_at !== null
            || $this->funds_committed_at !== null
            || $this->psn_committed_at !== null
            || $this->committed_at !== null;
    }
}
