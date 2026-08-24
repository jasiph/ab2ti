<?php

namespace App\Models;

use App\Enums\LapakModerationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Lapak extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\LapakFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'slug',
        'summary',
        'description',
        'location_text',
        'contact_phone',
        'contact_whatsapp',
        'contact_email',
    ];

    protected function casts(): array
    {
        return [
            'moderation_status' => LapakModerationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(LapakListing::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublicEligible(Builder $query): Builder
    {
        return $query->where('moderation_status', LapakModerationStatus::Published);
    }

    public function isPublicEligible(): bool
    {
        return $this->moderation_status->isPublicEligible();
    }

    public function hasContactChannel(): bool
    {
        return filled($this->contact_phone)
            || filled($this->contact_whatsapp)
            || filled($this->contact_email);
    }

    public function coverMedia(): ?Media
    {
        return $this->getFirstMedia('lapak-cover');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('lapak-cover')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(220)
            ->nonQueued();
    }
}
