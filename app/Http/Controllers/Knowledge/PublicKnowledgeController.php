<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use App\Support\Knowledge\KnowledgeArticleViews;
use App\Support\PublicDelivery\PublicContentSearch;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicKnowledgeController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $q = PublicContentSearch::fromRequest($request);

        $query = KnowledgeArticle::query()
            ->published()
            ->with('media');

        $articles = PublicContentSearch::constrain($query, $q)
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (KnowledgeArticle $article) => KnowledgeArticleViews::publicCard($article));

        return Inertia::render('Frontend/Knowledge/Index', [
            'articles' => $articles,
            'filters' => [
                'q' => $q,
            ],
            'seo' => $metadata->forPublicInformationalPage(
                'teknologi-tani',
                __('Teknologi Tani'),
                __('Knowledge intro'),
            )->toArray(),
        ]);
    }

    public function show(string $slug, PublicMetadataResolver $metadata): Response
    {
        $article = KnowledgeArticle::query()
            ->published()
            ->where('slug', $slug)
            ->with('media')
            ->first();

        abort_if($article === null, 404);

        $furtherReading = KnowledgeArticle::query()
            ->published()
            ->whereKeyNot($article->id)
            ->with('media')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (KnowledgeArticle $item) => KnowledgeArticleViews::publicCard($item))
            ->values();

        return Inertia::render('Frontend/Knowledge/Show', [
            'article' => KnowledgeArticleViews::publicDetail($article),
            'furtherReading' => $furtherReading,
            'seo' => $metadata->forKnowledgeArticle($article)->toArray(),
        ]);
    }
}
