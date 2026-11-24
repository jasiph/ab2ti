<?php

namespace App\Models;

use App\Enums\CooperativeMembershipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CooperativeMembership extends Model
{
    /** @use HasFactory<\Database\Factories\CooperativeMembershipFactory> */
    use HasFactory;

    protected $fillable = [
        'member_id',
        'status',
        'joined_at',
        'ended_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CooperativeMembershipStatus::class,
            'joined_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(BusinessParticipation::class);
    }

    public function psnParticipation(): HasOne
    {
        return $this->hasOne(BusinessParticipation::class)
            ->whereHas('business', fn ($query) => $query->psn());
    }

    public function fundEntries(): HasMany
    {
        return $this->hasMany(CooperativeFundEntry::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function applyStatusSideEffects(): void
    {
        if ($this->status === CooperativeMembershipStatus::Active) {
            $this->joined_at ??= now();
            $this->ended_at = null;

            return;
        }

        $this->ended_at ??= now();
    }
}
