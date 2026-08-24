<?php

namespace Tests\Unit\PublicDelivery;

use App\Support\PublicDelivery\PublicMetadataInput;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Tests\TestCase;

class PublicMetadataResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://ab2ti.test',
            'app.name' => 'AB2TI 2.0',
            'public_delivery.canonical_origin' => 'https://ab2ti.test',
            'public_delivery.site_name' => 'AB2TI 2.0',
            'public_delivery.default_title' => 'AB2TI 2.0',
            'public_delivery.default_description' => 'Asosiasi Bank Benih dan Teknologi Tani Indonesia (AB2TI).',
            'public_delivery.default_og_image_path' => '/images/og/ab2ti-og-default-v1.jpg',
        ]);
    }

    public function test_title_prefers_explicit_seo_then_resource_then_site_default(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('SEO Title', $resolver->resolve(new PublicMetadataInput(
            path: '/x',
            seoTitle: 'SEO Title',
            resourceTitle: 'Resource',
        ))->title);

        $this->assertSame('Resource', $resolver->resolve(new PublicMetadataInput(
            path: '/x',
            resourceTitle: 'Resource',
        ))->title);

        $this->assertSame('AB2TI 2.0', $resolver->resolve(new PublicMetadataInput(
            path: '/x',
        ))->title);
    }

    public function test_description_strips_html_and_falls_back(): void
    {
        $resolver = $this->resolver();

        $fromHtml = $resolver->resolve(new PublicMetadataInput(
            path: '/x',
            htmlContent: '<p>Hello <strong>world</strong> &amp; friends</p>',
        ));

        $this->assertSame('Hello world & friends', $fromHtml->description);

        $explicit = $resolver->resolve(new PublicMetadataInput(
            path: '/x',
            seoDescription: 'Editorial description',
            excerpt: 'Excerpt',
            htmlContent: '<p>Ignored</p>',
        ));

        $this->assertSame('Editorial description', $explicit->description);

        $default = $resolver->resolve(new PublicMetadataInput(path: '/x'));
        $this->assertSame(
            'Asosiasi Bank Benih dan Teknologi Tani Indonesia (AB2TI).',
            $default->description,
        );
    }

    public function test_explicit_og_image_wins_over_featured_then_default(): void
    {
        $resolver = $this->resolver();

        $explicit = $resolver->resolve(new PublicMetadataInput(
            path: '/x',
            explicitOgImageUrl: '/media/og/explicit.jpg',
            featuredImageUrl: '/media/og/featured.jpg',
        ));
        $this->assertSame('https://ab2ti.test/media/og/explicit.jpg', $explicit->ogImage);

        $featured = $resolver->resolve(new PublicMetadataInput(
            path: '/x',
            featuredImageUrl: 'http://evil.example/storage/cover.jpg',
        ));
        $this->assertSame('https://ab2ti.test/storage/cover.jpg', $featured->ogImage);

        $fallback = $resolver->resolve(new PublicMetadataInput(path: '/x'));
        $this->assertSame('https://ab2ti.test/images/og/ab2ti-og-default-v1.jpg', $fallback->ogImage);
        $this->assertSame(1200, $fallback->ogImageWidth);
        $this->assertSame(630, $fallback->ogImageHeight);
    }

    public function test_signed_or_temporary_image_urls_are_rejected(): void
    {
        $meta = $this->resolver()->resolve(new PublicMetadataInput(
            path: '/x',
            explicitOgImageUrl: 'https://ab2ti.test/storage/secret.jpg?expires=999999&signature=abc',
        ));

        $this->assertSame('https://ab2ti.test/images/og/ab2ti-og-default-v1.jpg', $meta->ogImage);
    }

    public function test_canonical_url_strips_query_strings(): void
    {
        $meta = $this->resolver()->resolve(new PublicMetadataInput(
            path: '/publikasi/edisi-1?utm_source=x',
        ));

        $this->assertSame('https://ab2ti.test/publikasi/edisi-1', $meta->canonicalUrl);
        $this->assertSame($meta->canonicalUrl, $meta->ogUrl);
    }

    public function test_canonical_path_drops_only_via_for_path_normalization(): void
    {
        $meta = $this->resolver()->resolve(new PublicMetadataInput(
            path: 'about/team/',
        ));

        $this->assertSame('https://ab2ti.test/about/team', $meta->canonicalUrl);
        $this->assertSame($meta->canonicalUrl, $meta->ogUrl);
    }

    public function test_unsupported_og_type_falls_back_to_website(): void
    {
        $meta = $this->resolver()->resolve(new PublicMetadataInput(
            path: '/x',
            ogType: 'profile',
        ));

        $this->assertSame('website', $meta->ogType);
    }

    public function test_private_surface_is_not_indexable(): void
    {
        $meta = $this->resolver()->resolve(new PublicMetadataInput(
            path: '/admin/pages',
            resourceTitle: 'Should not leak',
            indexable: false,
        ));

        $this->assertFalse($meta->indexable);
        $this->assertSame('noindex, nofollow', $meta->robots);
        $this->assertNotEmpty($meta->ogImage);
    }

    private function resolver(): PublicMetadataResolver
    {
        return $this->app->make(PublicMetadataResolver::class);
    }
}
