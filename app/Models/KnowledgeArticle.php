<?php

namespace App\Models;

use App\Enums\KnowledgeArticleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class KnowledgeArticle extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\KnowledgeArticleFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $fillable = [
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
            'status' => KnowledgeArticleStatus::class,
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
        return $query->where('status', KnowledgeArticleStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === KnowledgeArticleStatus::Published;
    }

    public function publicPath(): string
    {
        return 'teknologi-tani/'.$this->slug;
    }

    public function coverMedia(): ?Media
    {
        return $this->getFirstMedia('knowledge-cover');
    }

    public function socialOgMedia(): ?Media
    {
        return $this->getFirstMedia('social-og');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('knowledge-cover')->singleFile();
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
            ->performOnCollections('knowledge-cover', 'social-og')
            ->nonQueued();
    }
}
