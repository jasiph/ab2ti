<?php

namespace App\Support\PublicDelivery;

final class PublicMetadata
{
    public function __construct(
        public readonly string $title,
        public readonly string $documentTitle,
        public readonly string $description,
        public readonly string $canonicalUrl,
        public readonly string $ogType,
        public readonly string $ogTitle,
        public readonly string $ogDescription,
        public readonly string $ogUrl,
        public readonly string $ogImage,
        public readonly ?int $ogImageWidth,
        public readonly ?int $ogImageHeight,
        public readonly string $ogImageAlt,
        public readonly string $twitterCard,
        public readonly string $twitterTitle,
        public readonly string $twitterDescription,
        public readonly string $twitterImage,
        public readonly string $robots,
        public readonly bool $indexable,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'document_title' => $this->documentTitle,
            'description' => $this->description,
            'canonical_url' => $this->canonicalUrl,
            'og_type' => $this->ogType,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'og_url' => $this->ogUrl,
            'og_image' => $this->ogImage,
            'og_image_width' => $this->ogImageWidth,
            'og_image_height' => $this->ogImageHeight,
            'og_image_alt' => $this->ogImageAlt,
            'twitter_card' => $this->twitterCard,
            'twitter_title' => $this->twitterTitle,
            'twitter_description' => $this->twitterDescription,
            'twitter_image' => $this->twitterImage,
            'robots' => $this->robots,
            'indexable' => $this->indexable,
        ];
    }
}
