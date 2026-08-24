<?php

namespace App\Support\PublicDelivery;

final class PublicMetadataInput
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $seoTitle = null,
        public readonly ?string $resourceTitle = null,
        public readonly ?string $seoDescription = null,
        public readonly ?string $excerpt = null,
        public readonly ?string $htmlContent = null,
        public readonly ?string $explicitOgImageUrl = null,
        public readonly ?string $featuredImageUrl = null,
        public readonly string $ogType = 'website',
        public readonly bool $indexable = true,
        public readonly ?string $imageAlt = null,
    ) {}
}
