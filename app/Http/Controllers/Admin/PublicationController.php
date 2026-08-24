<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Publishing\PublishPublication;
use App\Actions\Publishing\UnpublishPublication;
use App\Enums\PublicationStatus;
use App\Enums\PublicationType;
use App\Exceptions\InvalidPublicationTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publishing\StorePublicationRequest;
use App\Http\Requests\Publishing\UpdatePublicationRequest;
use App\Models\Publication;
use App\Support\Editorial\EditorialContent;
use App\Support\PublicDelivery\PublicMetadataResolver;
use App\Support\Publishing\PublicationMedia;
use App\Support\Publishing\PublicationViews;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PublicationController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', Publication::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();

        $publications = Publication::query()
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->when(
                $status !== '' && PublicationStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $type !== '' && PublicationType::tryFrom($type) !== null,
                fn ($query) => $query->where('type', $type),
            )
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Publication $publication) => PublicationViews::adminListItem($publication));

        return Inertia::render('Admin/Publishing/Publications/Index', [
            'publications' => $publications,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
            ],
            'typeOptions' => PublicationViews::typeValues(),
            'statusOptions' => array_map(fn (PublicationStatus $item) => $item->value, PublicationStatus::cases()),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Daftar publikasi',
                'Halaman staf publikasi AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function create(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('create', Publication::class);

        return Inertia::render('Admin/Publishing/Publications/Form', [
            'publication' => null,
            'typeOptions' => PublicationViews::typeValues(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Buat publikasi',
                'Halaman staf publikasi AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function store(
        StorePublicationRequest $request,
        EditorialContent $content,
        PublicationMedia $media,
    ): RedirectResponse {
        $validated = $request->validated();

        $publication = DB::transaction(function () use ($validated, $request, $content, $media) {
            $publication = new Publication;
            $publication->fill([
                'type' => $validated['type'],
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'excerpt' => $validated['excerpt'] ?? null,
                'content' => $content->normalize($validated['content'] ?? null),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
            ]);
            $publication->status = PublicationStatus::Draft;
            $publication->created_by = $request->user()->id;
            $publication->updated_by = $request->user()->id;
            $publication->save();

            $media->syncCover(
                $publication,
                $request->file('new_cover'),
                $validated['existing_cover_asset_id'] ?? null,
                (bool) ($validated['remove_cover'] ?? false),
                $request->user()->id,
            );
            $media->syncSocialImage(
                $publication,
                $request->file('new_social_image'),
                $validated['existing_social_asset_id'] ?? null,
                (bool) ($validated['remove_social_image'] ?? false),
                $request->user()->id,
            );

            return $publication;
        });

        return redirect()
            ->route('admin.publishing.publications.edit', $publication)
            ->with('success', 'Draf publikasi disimpan.');
    }

    public function edit(Request $request, Publication $publication, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('view', $publication);

        $publication->load('media');

        return Inertia::render('Admin/Publishing/Publications/Form', [
            'publication' => PublicationViews::adminForm($publication, $request->user()),
            'typeOptions' => PublicationViews::typeValues(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Sunting publikasi',
                'Halaman staf publikasi AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function update(
        UpdatePublicationRequest $request,
        Publication $publication,
        EditorialContent $content,
        PublicationMedia $media,
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $publication, $content, $media) {
            $publication->fill([
                'type' => $validated['type'],
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'excerpt' => $validated['excerpt'] ?? null,
                'content' => $content->normalize($validated['content'] ?? null),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
            ]);
            $publication->updated_by = $request->user()->id;
            $publication->save();

            $media->syncCover(
                $publication,
                $request->file('new_cover'),
                $validated['existing_cover_asset_id'] ?? null,
                (bool) ($validated['remove_cover'] ?? false),
                $request->user()->id,
            );
            $media->syncSocialImage(
                $publication,
                $request->file('new_social_image'),
                $validated['existing_social_asset_id'] ?? null,
                (bool) ($validated['remove_social_image'] ?? false),
                $request->user()->id,
            );
        });

        return redirect()
            ->route('admin.publishing.publications.edit', $publication)
            ->with('success', 'Publikasi diperbarui.');
    }

    public function publish(
        Request $request,
        Publication $publication,
        PublishPublication $publish,
    ): RedirectResponse {
        $this->authorize('publish', $publication);
        $publish->handle($publication, $request->user());

        return redirect()
            ->route('admin.publishing.publications.edit', $publication)
            ->with('success', 'Publikasi diterbitkan.');
    }

    public function unpublish(
        Request $request,
        Publication $publication,
        UnpublishPublication $unpublish,
    ): RedirectResponse {
        $this->authorize('publish', $publication);
        $unpublish->handle($publication, $request->user());

        return redirect()
            ->route('admin.publishing.publications.edit', $publication)
            ->with('success', 'Publikasi dikembalikan ke draf.');
    }

    public function destroy(Publication $publication): RedirectResponse
    {
        $this->authorize('delete', $publication);

        if ($publication->isPublished()) {
            throw new InvalidPublicationTransition(
                'Publikasi yang sudah terbit harus dibatalkan terbitannya sebelum dihapus.',
            );
        }

        $publication->clearMediaCollection('publication-cover');
        $publication->clearMediaCollection('social-og');
        $publication->delete();

        return redirect()
            ->route('admin.publishing.publications.index')
            ->with('success', 'Draf publikasi dihapus.');
    }
}
