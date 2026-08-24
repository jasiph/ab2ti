<?php

namespace App\Http\Controllers;

use App\Models\Hero;
use App\Models\KnowledgeArticle;
use App\Models\LapakListing;
use App\Models\Publication;
use App\Models\WelcomeSection;
use App\Support\Knowledge\KnowledgeArticleViews;
use App\Support\Lapak\LapakViews;
use App\Support\Lapak\PublicLapakSearch;
use App\Support\PublicDelivery\PublicMetadataResolver;
use App\Support\PublicDelivery\SafePublicHref;
use App\Support\Publishing\PublicationViews;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(PublicMetadataResolver $metadata): Response
    {
        $hero = Hero::query()->active()->with('media')->inRandomOrder()->first();

        $heroData = null;
        if ($hero !== null) {
            $heroData = [
                'title' => $hero->title,
                'primary_text' => $hero->primary_text,
                'secondary_text' => $hero->secondary_text,
                'button_text' => $hero->button_text,
                'button_url' => SafePublicHref::accept($hero->button_url),
                'images' => $hero->getMedia('hero-backgrounds')
                    ->map(fn ($media) => $media->getUrl('large'))
                    ->values(),
            ];
        }

        $welcome = WelcomeSection::query()->active()->latest()->first();
        $welcomeSection = $welcome === null ? null : [
            'title' => $welcome->title,
            'content' => $welcome->content,
            'secondary_content' => $welcome->secondary_content,
            'image_url' => $welcome->image_url,
            'cta_label' => $welcome->cta_label,
            'cta_url' => SafePublicHref::accept($welcome->cta_url),
        ];

        $featuredImage = $heroData['images'][0] ?? $welcomeSection['image_url'] ?? null;

        $latestPublications = Publication::query()
            ->published()
            ->with('media')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Publication $publication) => PublicationViews::publicCard($publication))
            ->values();

        $latestKnowledgeArticles = KnowledgeArticle::query()
            ->published()
            ->with('media')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (KnowledgeArticle $article) => KnowledgeArticleViews::publicCard($article))
            ->values();

        $latestLapakListings = PublicLapakSearch::latest(3)
            ->map(fn (LapakListing $listing) => LapakViews::publicListingCard($listing))
            ->values();

        return Inertia::render('Frontend/Landing/Welcome', [
            'hero' => $heroData,
            'welcomeSection' => $welcomeSection,
            'latestPublications' => $latestPublications,
            'latestKnowledgeArticles' => $latestKnowledgeArticles,
            'latestLapakListings' => $latestLapakListings,
            'membershipCta' => [
                'url' => route('membership.index'),
                'learn_url' => route('membership.index'),
                'apply_url' => route('membership.apply'),
            ],
            'seo' => $metadata->forWelcome(
                $welcomeSection['title'] ?? (string) config('organization.name'),
                $welcomeSection['content'] ?? (string) config('public_delivery.default_description'),
                is_string($featuredImage) ? $featuredImage : null,
            )->toArray(),
        ]);
    }
}
