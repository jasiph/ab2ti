<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Cms\CmsPageViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Inertia\Inertia;

class PageController extends Controller
{
    public function show(string $slug, ?PublicMetadataResolver $publicMetadata = null)
    {
        $publicMetadata ??= app(PublicMetadataResolver::class);
        $page = Page::query()
            ->with('media')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $page) {
            abort(404);
        }

        return Inertia::render('Frontend/Page/Show', [
            'page' => CmsPageViews::publicDetail($page),
            'seo' => $publicMetadata->forCmsPage($page)->toArray(),
        ]);
    }
}
