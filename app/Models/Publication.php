<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Enums\PublicationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Publication extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\PublicationFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $fillable = [
        'type',
        'title',
        'slug',
        'excerpt',
        'content',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'type' => PublicationType::class,
            'status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublicationStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published;
    }

    public function publicPath(): string
    {
        return $this->type->publicPath().'/'.$this->slug;
    }

    public function coverMedia(): ?Media
    {
        return $this->getFirstMedia('publication-cover');
    }

    public function socialOgMedia(): ?Media
    {
        return $this->getFirstMedia('social-og');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('publication-cover')->singleFile();
        $this->addMediaCollection('social-og')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(220)
            ->nonQueued();

        $this->addMediaConversion('social-og')
            ->fit(Fit::Crop, 1200, 630)
            ->performOnCollections('publication-cover', 'social-og')
            ->nonQueued();
    }
}
