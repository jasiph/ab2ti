<?php

namespace App\Support\Cms;

use App\Models\Page;
use App\Support\Editorial\EditorialContent;
use App\Support\PublicDelivery\SafePublicHref;

final class CmsPageViews
{
    /**
     * @return array<string, mixed>
     */
    public static function publicDetail(Page $page): array
    {
        $cover = $page->getFirstMedia('page-cover');

        return [
            'title' => $page->title,
            'slug' => $page->slug,
            'content' => app(EditorialContent::class)->render($page->content),
            'cover_url' => $cover?->getUrl(),
            'cover_alt' => $page->title,
            'related_links' => self::relatedLinks($page),
        ];
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private static function relatedLinks(Page $page): array
    {
        $raw = $page->settings['related_links'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $links = [];

        foreach ($raw as $link) {
            if (! is_array($link)) {
                continue;
            }

            $url = is_string($link['url'] ?? null) ? trim($link['url']) : '';
            $label = is_string($link['label'] ?? null) ? trim($link['label']) : $url;

            if ($url === '' || $label === '' || SafePublicHref::accept($url) === null) {
                continue;
            }

            $links[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $links;
    }
}
