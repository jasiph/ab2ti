<?php

namespace App\Models;

use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Enums\LapakPriceMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class LapakListing extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\LapakListingFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description',
        'price_mode',
        'price_amount',
        'price_unit',
    ];

    protected function casts(): array
    {
        return [
            'price_mode' => LapakPriceMode::class,
            'price_amount' => 'decimal:2',
            'availability' => LapakListingAvailability::class,
            'moderation_status' => LapakModerationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function lapak(): BelongsTo
    {
        return $this->belongsTo(Lapak::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LapakCategory::class, 'category_id');
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
        return $query
            ->where('moderation_status', LapakModerationStatus::Published)
            ->where('availability', LapakListingAvailability::Available)
            ->whereHas('lapak', fn (Builder $lapak) => $lapak->publicEligible())
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true));
    }

    public function isPublicEligible(): bool
    {
        $this->loadMissing(['lapak', 'category']);

        return $this->moderation_status->isPublicEligible()
            && $this->availability === LapakListingAvailability::Available
            && $this->lapak?->isPublicEligible() === true
            && $this->category?->is_active === true;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Media>
     */
    public function galleryMedia()
    {
        return $this->getMedia('listing-images');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('listing-images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->nonQueued();
    }
}
