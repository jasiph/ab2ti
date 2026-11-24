<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessParticipation extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessParticipationFactory> */
    use HasFactory;

    protected $fillable = [
        'cooperative_membership_id',
        'cooperative_business_id',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(CooperativeMembership::class, 'cooperative_membership_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(CooperativeBusiness::class, 'cooperative_business_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ParticipationEntry::class)->orderBy('id');
    }

    public function currentUnits(): int
    {
        if (array_key_exists('units_sum', $this->attributes) && $this->attributes['units_sum'] !== null) {
            return (int) $this->attributes['units_sum'];
        }

        if ($this->relationLoaded('entries')) {
            return (int) $this->entries->sum('units');
        }

        return $this->ledgerUnitsSum();
    }

    public function ledgerUnitsSum(): int
    {
        return (int) $this->entries()->toBase()->sum('units');
    }

    public function currentValue(): int
    {
        $business = $this->relationLoaded('business')
            ? $this->business
            : $this->business()->first();

        if ($business === null) {
            return 0;
        }

        return $this->currentUnits() * (int) $business->current_unit_value;
    }

    public function latestShareReference(): ?string
    {
        $entry = $this->entries()
            ->whereNotNull('share_reference')
            ->orderByDesc('id')
            ->first();

        return $entry?->share_reference;
    }
}
