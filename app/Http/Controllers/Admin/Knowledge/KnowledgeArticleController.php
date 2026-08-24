<?php

namespace App\Http\Controllers\Admin\Knowledge;

use App\Actions\Knowledge\PublishKnowledgeArticle;
use App\Actions\Knowledge\UnpublishKnowledgeArticle;
use App\Enums\KnowledgeArticleStatus;
use App\Exceptions\InvalidKnowledgeArticleTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\StoreKnowledgeArticleRequest;
use App\Http\Requests\Knowledge\UpdateKnowledgeArticleRequest;
use App\Models\KnowledgeArticle;
use App\Support\Editorial\EditorialContent;
use App\Support\Knowledge\KnowledgeArticleMedia;
use App\Support\Knowledge\KnowledgeArticleViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeArticleController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', KnowledgeArticle::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $articles = KnowledgeArticle::query()
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->when(
                $status !== '' && KnowledgeArticleStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (KnowledgeArticle $article) => KnowledgeArticleViews::adminListItem($article));

        return Inertia::render('Admin/Knowledge/Articles/Index', [
            'articles' => $articles,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => array_map(fn (KnowledgeArticleStatus $item) => $item->value, KnowledgeArticleStatus::cases()),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Daftar artikel pengetahuan',
                'Halaman staf Teknologi Tani AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function create(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('create', KnowledgeArticle::class);

        return Inertia::render('Admin/Knowledge/Articles/Form', [
            'article' => null,
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Buat artikel pengetahuan',
                'Halaman staf Teknologi Tani AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function store(
        StoreKnowledgeArticleRequest $request,
        EditorialContent $content,
        KnowledgeArticleMedia $media,
    ): RedirectResponse {
        $validated = $request->validated();

        $article = DB::transaction(function () use ($validated, $request, $content, $media) {
            $article = new KnowledgeArticle;
            $article->fill([
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'excerpt' => $validated['excerpt'] ?? null,
                'content' => $content->normalize($validated['content'] ?? null),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
            ]);
            $article->status = KnowledgeArticleStatus::Draft;
            $article->created_by = $request->user()->id;
            $article->updated_by = $request->user()->id;
            $article->save();

            $media->syncCover(
                $article,
                $request->file('new_cover'),
                $validated['existing_cover_asset_id'] ?? null,
                (bool) ($validated['remove_cover'] ?? false),
                $request->user()->id,
            );
            $media->syncSocialImage(
                $article,
                $request->file('new_social_image'),
                $validated['existing_social_asset_id'] ?? null,
                (bool) ($validated['remove_social_image'] ?? false),
                $request->user()->id,
            );

            return $article;
        });

        return redirect()
            ->route('admin.knowledge.articles.edit', $article)
            ->with('success', 'Draf artikel pengetahuan disimpan.');
    }

    public function edit(Request $request, KnowledgeArticle $article, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('view', $article);

        $article->load('media');

        return Inertia::render('Admin/Knowledge/Articles/Form', [
            'article' => KnowledgeArticleViews::adminForm($article, $request->user()),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Sunting artikel pengetahuan',
                'Halaman staf Teknologi Tani AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function update(
        UpdateKnowledgeArticleRequest $request,
        KnowledgeArticle $article,
        EditorialContent $content,
        KnowledgeArticleMedia $media,
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $article, $content, $media) {
            $article->fill([
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'excerpt' => $validated['excerpt'] ?? null,
                'content' => $content->normalize($validated['content'] ?? null),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
            ]);
            $article->updated_by = $request->user()->id;
            $article->save();

            $media->syncCover(
                $article,
                $request->file('new_cover'),
                $validated['existing_cover_asset_id'] ?? null,
                (bool) ($validated['remove_cover'] ?? false),
                $request->user()->id,
            );
            $media->syncSocialImage(
                $article,
                $request->file('new_social_image'),
                $validated['existing_social_asset_id'] ?? null,
                (bool) ($validated['remove_social_image'] ?? false),
                $request->user()->id,
            );
        });

        return redirect()
            ->route('admin.knowledge.articles.edit', $article)
            ->with('success', 'Artikel pengetahuan diperbarui.');
    }

    public function publish(
        Request $request,
        KnowledgeArticle $article,
        PublishKnowledgeArticle $publish,
    ): RedirectResponse {
        $this->authorize('publish', $article);
        $publish->handle($article, $request->user());

        return redirect()
            ->route('admin.knowledge.articles.edit', $article)
            ->with('success', 'Artikel pengetahuan diterbitkan.');
    }

    public function unpublish(
        Request $request,
        KnowledgeArticle $article,
        UnpublishKnowledgeArticle $unpublish,
    ): RedirectResponse {
        $this->authorize('publish', $article);
        $unpublish->handle($article, $request->user());

        return redirect()
            ->route('admin.knowledge.articles.edit', $article)
            ->with('success', 'Artikel pengetahuan dikembalikan ke draf.');
    }

    public function destroy(KnowledgeArticle $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        if ($article->isPublished()) {
            throw new InvalidKnowledgeArticleTransition(
                'Artikel pengetahuan yang sudah terbit harus dibatalkan terbitannya sebelum dihapus.',
            );
        }

        $article->clearMediaCollection('knowledge-cover');
        $article->clearMediaCollection('social-og');
        $article->delete();

        return redirect()
            ->route('admin.knowledge.articles.index')
            ->with('success', 'Draf artikel pengetahuan dihapus.');
    }
}
