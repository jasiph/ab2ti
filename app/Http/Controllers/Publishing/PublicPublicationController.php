<?php

namespace App\Http\Controllers\Publishing;

use App\Enums\PublicationType;
use App\Http\Controllers\Controller;
use App\Models\Publication;
use App\Support\PublicDelivery\PublicContentSearch;
use App\Support\PublicDelivery\PublicMetadataResolver;
use App\Support\Publishing\PublicationViews;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicPublicationController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        return $this->list($request, $metadata, null);
    }

    public function typeIndex(Request $request, string $type, PublicMetadataResolver $metadata): Response
    {
        $publicationType = PublicationType::fromPublicPath($type);
        abort_if($publicationType === null, 404);

        return $this->list($request, $metadata, $publicationType);
    }

    public function show(string $type, string $slug, PublicMetadataResolver $metadata): Response
    {
        $publicationType = PublicationType::fromPublicPath($type);
        abort_if($publicationType === null, 404);

        $publication = Publication::query()
            ->published()
            ->where('type', $publicationType)
            ->where('slug', $slug)
            ->with('media')
            ->first();

        abort_if($publication === null, 404);

        $related = Publication::query()
            ->published()
            ->where('type', $publication->type)
            ->whereKeyNot($publication->id)
            ->with('media')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Publication $item) => PublicationViews::publicCard($item))
            ->values();

        return Inertia::render('Frontend/Publishing/Show', [
            'publication' => PublicationViews::publicDetail($publication),
            'related' => $related,
            'seo' => $metadata->forPublication($publication)->toArray(),
        ]);
    }

    private function list(Request $request, PublicMetadataResolver $metadata, ?PublicationType $type): Response
    {
        $q = PublicContentSearch::fromRequest($request);

        $query = Publication::query()
            ->published()
            ->with('media')
            ->when($type !== null, fn ($builder) => $builder->where('type', $type));

        $publications = PublicContentSearch::constrain($query, $q)
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Publication $publication) => PublicationViews::publicCard($publication));

        $path = $type?->publicPath() ?? 'publikasi';
        $title = $type === null ? __('Publications') : $type->label();

        return Inertia::render('Frontend/Publishing/Index', [
            'publications' => $publications,
            'activeType' => $type?->value,
            'typeOptions' => PublicationViews::publicTypeOptions(),
            'filters' => [
                'q' => $q,
            ],
            'seo' => $metadata->forPublicInformationalPage(
                $path,
                $title,
                __('Publications intro'),
            )->toArray(),
        ]);
    }
}
