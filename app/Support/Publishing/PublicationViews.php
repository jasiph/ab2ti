<?php

namespace App\Support\Publishing;

use App\Enums\PublicationType;
use App\Models\Publication;
use App\Models\User;
use App\Support\Editorial\EditorialContent;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class PublicationViews
{
    /**
     * @return array<string, mixed>
     */
    public static function publicCard(Publication $publication): array
    {
        return [
            'id' => $publication->id,
            'type' => $publication->type->value,
            'type_path' => $publication->type->publicPath(),
            'type_label' => $publication->type->label(),
            'title' => $publication->title,
            'slug' => $publication->slug,
            'excerpt' => $publication->excerpt,
            'published_at' => $publication->published_at?->toIso8601String(),
            'url' => '/'.$publication->publicPath(),
            'cover_url' => self::cardCoverUrl($publication->coverMedia()),
            'cover_alt' => $publication->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicDetail(Publication $publication): array
    {
        $cover = $publication->coverMedia();

        return [
            'id' => $publication->id,
            'type' => $publication->type->value,
            'type_path' => $publication->type->publicPath(),
            'type_label' => $publication->type->label(),
            'title' => $publication->title,
            'excerpt' => $publication->excerpt,
            'content' => app(EditorialContent::class)->render($publication->content),
            'published_at' => $publication->published_at?->toIso8601String(),
            'cover_url' => $cover?->getUrl(),
            'cover_alt' => $publication->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminListItem(Publication $publication): array
    {
        return [
            'id' => $publication->id,
            'title' => $publication->title,
            'type' => $publication->type->value,
            'status' => $publication->status->value,
            'published_at' => $publication->published_at?->toIso8601String(),
            'updated_at' => $publication->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminForm(Publication $publication, User $actor): array
    {
        $cover = $publication->coverMedia();
        $og = $publication->socialOgMedia();
        $resolver = app(PublicMetadataResolver::class);

        return [
            'id' => $publication->id,
            'type' => $publication->type->value,
            'title' => $publication->title,
            'slug' => $publication->slug,
            'excerpt' => $publication->excerpt,
            'content' => app(EditorialContent::class)->normalize($publication->content),
            'status' => $publication->status->value,
            'published_at' => $publication->published_at?->toIso8601String(),
            'meta_title' => $publication->meta_title,
            'meta_description' => $publication->meta_description,
            'cover_url' => $cover ? $resolver->urlFromMedia($cover) : null,
            'social_image_url' => $og ? $resolver->urlFromMedia($og) : null,
            'public_url' => $publication->isPublished() ? '/'.$publication->publicPath() : null,
            'updated_at' => $publication->updated_at?->toIso8601String(),
            'can_update' => $actor->can('update', $publication),
            'can_publish' => $actor->can('publish', $publication),
            'can_delete' => $actor->can('delete', $publication),
        ];
    }

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return array_map(fn (PublicationType $type) => $type->value, PublicationType::cases());
    }

    /**
     * @return list<array{value: string, path: string, label: string}>
     */
    public static function publicTypeOptions(): array
    {
        return array_map(fn (PublicationType $type): array => [
            'value' => $type->value,
            'path' => $type->publicPath(),
            'label' => $type->label(),
        ], PublicationType::cases());
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
