<?php

namespace App\Http\Controllers\Lapak;

use App\Http\Controllers\Controller;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Support\Lapak\LapakViews;
use App\Support\Lapak\PublicLapakSearch;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicLapakController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $filters = PublicLapakSearch::filters($request);

        $listings = PublicLapakSearch::listings($request)
            ->through(fn (LapakListing $listing) => LapakViews::publicListingCard($listing));

        $categories = LapakCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->map(fn (LapakCategory $category) => [
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->values();

        return Inertia::render('Frontend/Lapak/Index', [
            'listings' => $listings,
            'categories' => $categories,
            'filters' => [
                'q' => $filters['term'],
                'kategori' => $filters['category'],
            ],
            'seo' => $metadata->forPublicInformationalPage(
                'lapak',
                __('Lapak Anggota'),
                __('Lapak intro'),
            )->toArray(),
        ]);
    }

    public function show(string $lapak, PublicMetadataResolver $metadata): Response
    {
        $storefront = Lapak::query()
            ->publicEligible()
            ->where('slug', $lapak)
            ->with('media')
            ->first();

        abort_if($storefront === null, 404);

        $listings = PublicLapakSearch::storefrontListings($storefront)
            ->through(fn (LapakListing $listing) => LapakViews::publicListingCard($listing));

        return Inertia::render('Frontend/Lapak/Show', [
            'lapak' => LapakViews::publicStorefront($storefront),
            'listings' => $listings,
            'seo' => $metadata->forLapak($storefront)->toArray(),
        ]);
    }

    public function listing(string $lapak, string $listing, PublicMetadataResolver $metadata): Response
    {
        $storefront = Lapak::query()
            ->publicEligible()
            ->where('slug', $lapak)
            ->with('media')
            ->first();

        abort_if($storefront === null, 404);

        $item = $storefront->listings()
            ->publicEligible()
            ->where('slug', $listing)
            ->with(['category', 'media', 'lapak.media'])
            ->first();

        abort_if($item === null, 404);

        return Inertia::render('Frontend/Lapak/Listing', [
            'listing' => LapakViews::publicListingDetail($item),
            'seo' => $metadata->forLapakListing($item)->toArray(),
        ]);
    }
}
