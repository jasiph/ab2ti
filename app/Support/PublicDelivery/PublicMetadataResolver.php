<?php

namespace App\Support\PublicDelivery;

use App\Models\KnowledgeArticle;
use App\Models\Lapak;
use App\Models\LapakListing;
use App\Models\Page;
use App\Models\Publication;
use App\Support\CmsPath;
use App\Support\Lapak\LapakViews;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class PublicMetadataResolver
{
    public function __construct(
        private readonly CanonicalUrl $canonicalUrl,
    ) {}

    public function resolve(PublicMetadataInput $input): PublicMetadata
    {
        $siteName = (string) config('public_delivery.site_name');
        $title = $this->resolveTitle($input, $siteName);
        $description = $this->resolveDescription($input);
        $canonical = $this->canonicalUrl->forPath($input->path);
        $image = $this->resolveImage($input);
        $indexable = $input->indexable;
        $documentTitle = $title === $siteName ? $siteName : $title.' - '.$siteName;

        return new PublicMetadata(
            title: $title,
            documentTitle: $documentTitle,
            description: $description,
            canonicalUrl: $canonical,
            ogType: $this->safeOgType($input->ogType),
            ogTitle: $title,
            ogDescription: $description,
            ogUrl: $canonical,
            ogImage: $image['url'],
            ogImageWidth: $image['width'],
            ogImageHeight: $image['height'],
            ogImageAlt: $image['alt'],
            twitterCard: (string) config('public_delivery.twitter_card', 'summary_large_image'),
            twitterTitle: $title,
            twitterDescription: $description,
            twitterImage: $image['url'],
            robots: $indexable
                ? (string) config('public_delivery.indexable_robots')
                : (string) config('public_delivery.private_robots'),
            indexable: $indexable,
        );
    }

    public function forRequest(Request $request): PublicMetadata
    {
        $private = $this->isPrivateSurface($request);

        return $this->resolve(new PublicMetadataInput(
            path: $request->path(),
            indexable: ! $private,
        ));
    }

    public function forCmsPage(Page $page): PublicMetadata
    {
        $explicit = $page->getFirstMedia('social-og');
        $featured = $page->getFirstMedia('page-cover');

        return $this->resolve(new PublicMetadataInput(
            path: '/'.$page->slug,
            seoTitle: $page->meta_title,
            resourceTitle: $page->title,
            seoDescription: $page->meta_description,
            htmlContent: $page->content,
            explicitOgImageUrl: $this->urlFromMedia($explicit),
            featuredImageUrl: $this->urlFromMedia($featured),
            ogType: 'website',
            indexable: true,
            imageAlt: $page->title,
        ));
    }

    public function forWelcome(?string $welcomeTitle, ?string $welcomeHtml, ?string $featuredImageUrl): PublicMetadata
    {
        return $this->resolve(new PublicMetadataInput(
            path: '/',
            resourceTitle: $welcomeTitle,
            htmlContent: $welcomeHtml,
            featuredImageUrl: $featuredImageUrl,
            ogType: 'website',
            indexable: true,
            imageAlt: $welcomeTitle,
        ));
    }

    public function forPublicInformationalPage(string $path, string $title, string $description): PublicMetadata
    {
        return $this->resolve(new PublicMetadataInput(
            path: $path,
            seoTitle: $title,
            seoDescription: $description,
            ogType: 'website',
            indexable: true,
        ));
    }

    public function forTransactionalPage(string $path, string $genericTitle, string $genericDescription): PublicMetadata
    {
        return $this->resolve(new PublicMetadataInput(
            path: $path,
            seoTitle: $genericTitle,
            seoDescription: $genericDescription,
            ogType: 'website',
            indexable: false,
        ));
    }

    public function forPublication(Publication $publication): PublicMetadata
    {
        $explicit = $publication->socialOgMedia();
        $featured = $publication->coverMedia();

        return $this->resolve(new PublicMetadataInput(
            path: '/'.$publication->publicPath(),
            seoTitle: $publication->meta_title,
            resourceTitle: $publication->title,
            seoDescription: $publication->meta_description,
            excerpt: $publication->excerpt,
            htmlContent: $publication->content,
            explicitOgImageUrl: $this->urlFromMedia($explicit),
            featuredImageUrl: $this->urlFromMedia($featured),
            ogType: 'article',
            indexable: $publication->isPublished(),
            imageAlt: $publication->title,
        ));
    }

    public function forKnowledgeArticle(KnowledgeArticle $article): PublicMetadata
    {
        $explicit = $article->socialOgMedia();
        $featured = $article->coverMedia();

        return $this->resolve(new PublicMetadataInput(
            path: '/'.$article->publicPath(),
            seoTitle: $article->meta_title,
            resourceTitle: $article->title,
            seoDescription: $article->meta_description,
            excerpt: $article->excerpt,
            htmlContent: $article->content,
            explicitOgImageUrl: $this->urlFromMedia($explicit),
            featuredImageUrl: $this->urlFromMedia($featured),
            ogType: 'article',
            indexable: $article->isPublished(),
            imageAlt: $article->title,
        ));
    }

    public function forLapak(Lapak $lapak): PublicMetadata
    {
        $cover = $lapak->coverMedia();

        return $this->resolve(new PublicMetadataInput(
            path: '/lapak/'.$lapak->slug,
            resourceTitle: $lapak->name,
            excerpt: $lapak->summary,
            htmlContent: $lapak->description,
            featuredImageUrl: $this->urlFromMedia($cover),
            ogType: 'website',
            indexable: $lapak->isPublicEligible(),
            imageAlt: $lapak->name,
        ));
    }

    public function forLapakListing(LapakListing $listing): PublicMetadata
    {
        $listing->loadMissing('lapak');
        $image = $listing->galleryMedia()->first() ?: $listing->lapak?->coverMedia();
        $lapakName = $listing->lapak?->name;
        $title = $lapakName
            ? $listing->title.' — '.$lapakName
            : $listing->title;

        return $this->resolve(new PublicMetadataInput(
            path: '/'.LapakViews::listingPublicPath($listing),
            seoTitle: $title,
            excerpt: $listing->summary,
            htmlContent: $listing->description,
            featuredImageUrl: $this->urlFromMedia($image),
            ogType: 'website',
            indexable: $listing->isPublicEligible(),
            imageAlt: $listing->title,
        ));
    }

    public function urlFromMedia(?Media $media): ?string
    {
        if ($media === null) {
            return null;
        }

        try {
            if ($media->hasGeneratedConversion('social-og')) {
                return $media->getUrl('social-og');
            }

            return $media->getUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    public function isPrivateSurface(Request $request): bool
    {
        $trimmed = trim($request->path(), '/');

        if ($trimmed === '') {
            return false;
        }

        $first = explode('/', $trimmed)[0];

        return in_array($first, CmsPath::reservedSegmentNames(), true);
    }

    private function resolveTitle(PublicMetadataInput $input, string $siteName): string
    {
        $candidate = $this->firstNonEmpty([
            $input->seoTitle,
            $input->resourceTitle,
            (string) config('public_delivery.default_title', $siteName),
        ]);

        return $this->plainText($candidate, (int) config('public_delivery.title_max_length', 120));
    }

    private function resolveDescription(PublicMetadataInput $input): string
    {
        $fromHtml = $this->plainText((string) $input->htmlContent, (int) config('public_delivery.description_max_length', 160));

        $candidate = $this->firstNonEmpty([
            $input->seoDescription,
            $input->excerpt,
            $fromHtml !== '' ? $fromHtml : null,
            (string) config('public_delivery.default_description'),
        ]);

        return $this->plainText($candidate, (int) config('public_delivery.description_max_length', 160));
    }

    /**
     * @return array{url: string, width: int|null, height: int|null, alt: string}
     */
    private function resolveImage(PublicMetadataInput $input): array
    {
        $alt = $this->plainText(
            $input->imageAlt ?: (string) config('public_delivery.default_og_image_alt', 'AB2TI'),
            120,
        );

        foreach ([$input->explicitOgImageUrl, $input->featuredImageUrl] as $candidate) {
            $url = $this->absolutizePublicImageUrl($candidate);

            if ($url === null) {
                continue;
            }

            $knownOgDerivative = str_contains($url, 'social-og');

            return [
                'url' => $url,
                'width' => $knownOgDerivative ? (int) config('public_delivery.og_image_width') : null,
                'height' => $knownOgDerivative ? (int) config('public_delivery.og_image_height') : null,
                'alt' => $alt !== '' ? $alt : (string) config('public_delivery.default_og_image_alt'),
            ];
        }

        $defaultPath = (string) config('public_delivery.default_og_image_path');

        return [
            'url' => $this->canonicalUrl->origin().$defaultPath,
            'width' => (int) config('public_delivery.og_image_width'),
            'height' => (int) config('public_delivery.og_image_height'),
            'alt' => (string) config('public_delivery.default_og_image_alt'),
        ];
    }

    private function absolutizePublicImageUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || $this->looksSignedOrTemporary($url)) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $path = '/'.ltrim($url, '/');
        } else {
            $path = parse_url($url, PHP_URL_PATH);

            if (! is_string($path) || $path === '') {
                return null;
            }
        }

        return $this->canonicalUrl->origin().$path;
    }

    private function looksSignedOrTemporary(string $url): bool
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return false;
        }

        parse_str($query, $params);

        foreach (['signature', 'expires', 'token'] as $key) {
            if (array_key_exists($key, $params)) {
                return true;
            }
        }

        return false;
    }

    private function safeOgType(string $ogType): string
    {
        $allowed = ['website', 'article'];

        return in_array($ogType, $allowed, true) ? $ogType : 'website';
    }

    /**
     * @param  list<string|null>  $candidates
     */
    private function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $plain = $this->plainText($candidate, 10000);

            if ($plain !== '') {
                return $plain;
            }
        }

        return '';
    }

    private function plainText(string $value, int $maxLength): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return Str::limit($text, $maxLength, '');
    }
}
