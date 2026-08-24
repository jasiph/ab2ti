<?php

namespace App\Support\Knowledge;

use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Support\Editorial\EditorialContent;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class KnowledgeArticleViews
{
    /**
     * @return array<string, mixed>
     */
    public static function publicCard(KnowledgeArticle $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'published_at' => $article->published_at?->toIso8601String(),
            'url' => '/'.$article->publicPath(),
            'cover_url' => self::cardCoverUrl($article->coverMedia()),
            'cover_alt' => $article->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicDetail(KnowledgeArticle $article): array
    {
        $cover = $article->coverMedia();

        return [
            'id' => $article->id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'content' => app(EditorialContent::class)->render($article->content),
            'published_at' => $article->published_at?->toIso8601String(),
            'cover_url' => $cover?->getUrl(),
            'cover_alt' => $article->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminListItem(KnowledgeArticle $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'status' => $article->status->value,
            'published_at' => $article->published_at?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminForm(KnowledgeArticle $article, User $actor): array
    {
        $cover = $article->coverMedia();
        $og = $article->socialOgMedia();
        $resolver = app(PublicMetadataResolver::class);

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'content' => app(EditorialContent::class)->normalize($article->content),
            'status' => $article->status->value,
            'published_at' => $article->published_at?->toIso8601String(),
            'meta_title' => $article->meta_title,
            'meta_description' => $article->meta_description,
            'cover_url' => $cover ? $resolver->urlFromMedia($cover) : null,
            'social_image_url' => $og ? $resolver->urlFromMedia($og) : null,
            'public_url' => $article->isPublished() ? '/'.$article->publicPath() : null,
            'updated_at' => $article->updated_at?->toIso8601String(),
            'can_update' => $actor->can('update', $article),
            'can_publish' => $actor->can('publish', $article),
            'can_delete' => $actor->can('delete', $article),
        ];
    }

    private static function cardCoverUrl(?Media $cover): ?string
    {
        if ($cover === null) {
            return null;
        }

        return $cover->hasGeneratedConversion('thumb')
            ? $cover->getUrl('thumb')
            : $cover->getUrl();
    }
}
