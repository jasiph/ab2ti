<?php

namespace App\Support\PublicDelivery;

use App\Enums\PublicationType;
use App\Models\KnowledgeArticle;
use App\Models\Lapak;
use App\Models\LapakListing;
use App\Models\Page;
use App\Models\Publication;
use App\Support\CmsPath;
use App\Support\Lapak\LapakViews;
use Carbon\CarbonInterface;

final class PublicSitemap
{
    public function __construct(
        private readonly CanonicalUrl $canonicalUrl,
    ) {}

    public function toXml(): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($this->entries() as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.$this->escape($entry['loc']).'</loc>';

            if (isset($entry['lastmod'])) {
                $lines[] = '    <lastmod>'.$this->escape($entry['lastmod']).'</lastmod>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @return list<array{loc: string, lastmod?: string}>
     */
    public function entries(): array
    {
        $entries = [];

        $this->push($entries, '/');
        $this->push($entries, '/publikasi');

        foreach (PublicationType::cases() as $type) {
            $this->push($entries, '/'.$type->publicPath());
        }

        $this->push($entries, '/teknologi-tani');
        $this->push($entries, '/lapak');
        $this->push($entries, '/keanggotaan');
        $this->push($entries, '/koperasi');

        Page::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->select(['id', 'slug', 'updated_at'])
            ->chunkById(200, function ($pages) use (&$entries): void {
                foreach ($pages as $page) {
                    if (! is_string($page->slug) || $page->slug === '' || CmsPath::isReserved($page->slug)) {
                        continue;
                    }

                    $this->push($entries, '/'.$page->slug, $page->updated_at);
                }
            });

        Publication::query()
            ->published()
            ->orderBy('id')
            ->select(['id', 'type', 'slug', 'updated_at', 'published_at'])
            ->chunkById(200, function ($publications) use (&$entries): void {
                foreach ($publications as $publication) {
                    $this->push($entries, '/'.$publication->publicPath(), $publication->published_at ?? $publication->updated_at);
                }
            });

        KnowledgeArticle::query()
            ->published()
            ->orderBy('id')
            ->select(['id', 'slug', 'updated_at', 'published_at'])
            ->chunkById(200, function ($articles) use (&$entries): void {
                foreach ($articles as $article) {
                    $this->push($entries, '/'.$article->publicPath(), $article->published_at ?? $article->updated_at);
                }
            });

        Lapak::query()
            ->publicEligible()
            ->orderBy('id')
            ->select(['id', 'slug', 'updated_at', 'published_at'])
            ->chunkById(200, function ($storefronts) use (&$entries): void {
                foreach ($storefronts as $storefront) {
                    $this->push($entries, '/lapak/'.$storefront->slug, $storefront->published_at ?? $storefront->updated_at);
                }
            });

        LapakListing::query()
            ->publicEligible()
            ->with(['lapak:id,slug'])
            ->orderBy('id')
            ->select(['id', 'lapak_id', 'slug', 'updated_at', 'published_at'])
            ->chunkById(200, function ($listings) use (&$entries): void {
                foreach ($listings as $listing) {
                    if ($listing->lapak === null) {
                        continue;
                    }

                    $this->push(
                        $entries,
                        '/'.LapakViews::listingPublicPath($listing),
                        $listing->published_at ?? $listing->updated_at,
                    );
                }
            });

        return $entries;
    }

    /**
     * @param  list<array{loc: string, lastmod?: string}>  $entries
     */
    private function push(array &$entries, string $path, mixed $lastmod = null): void
    {
        $entry = [
            'loc' => $this->canonicalUrl->forPath($path),
        ];

        if ($lastmod instanceof CarbonInterface) {
            $entry['lastmod'] = $lastmod->toAtomString();
        }

        $entries[] = $entry;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
