<?php

namespace App\Http\Controllers\Admin\Lapak;

use App\Actions\Lapak\CreateLapakListing;
use App\Actions\Lapak\TransitionLapakListingModeration;
use App\Actions\Lapak\UpdateLapakListing;
use App\Actions\Lapak\UpdateLapakListingAvailability;
use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lapak\StoreLapakListingRequest;
use App\Http\Requests\Lapak\UpdateLapakListingRequest;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Support\Lapak\LapakViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LapakListingController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', LapakListing::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $availability = $request->string('availability')->toString();
        $lapakId = $request->integer('lapak_id') ?: null;
        $categoryId = $request->integer('category_id') ?: null;

        $listings = LapakListing::query()
            ->with(['lapak.member', 'category'])
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->when(
                $status !== '' && LapakModerationStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('moderation_status', $status),
            )
            ->when(
                $availability !== '' && LapakListingAvailability::tryFrom($availability) !== null,
                fn ($query) => $query->where('availability', $availability),
            )
            ->when($lapakId, fn ($query) => $query->where('lapak_id', $lapakId))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LapakListing $listing) => LapakViews::listingListItem($listing));

        return Inertia::render('Admin/Lapak/Listings/Index', [
            'listings' => $listings,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'availability' => $availability,
                'lapak_id' => $lapakId,
                'category_id' => $categoryId,
            ],
            'statusOptions' => LapakViews::moderationOptions(),
            'availabilityOptions' => LapakViews::availabilityOptions(),
            'lapakOptions' => $this->lapakOptions(),
            'categoryOptions' => $this->categoryOptions(includeInactive: true),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Daftar produk Lapak',
                'Halaman staf Lapak AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function create(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('create', LapakListing::class);

        return Inertia::render('Admin/Lapak/Listings/Form', [
            'listing' => null,
            'prefillLapakId' => $request->integer('lapak_id') ?: null,
            'lapakOptions' => $this->lapakOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'priceModeOptions' => LapakViews::priceModeOptions(),
            'availabilityOptions' => LapakViews::availabilityOptions(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Tambah produk Lapak',
                'Halaman staf Lapak AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function store(StoreLapakListingRequest $request, CreateLapakListing $create): RedirectResponse
    {
        $listing = $create->handle(
            $request->payload(),
            $request->user(),
            $request->file('new_image'),
            $request->input('existing_image_asset_ids', []) ?: [],
        );

        return redirect()
            ->route('admin.lapak.listings.show', $listing)
            ->with('success', 'Produk dibuat sebagai draf.');
    }

    public function show(Request $request, LapakListing $listing, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('view', $listing);

        return Inertia::render('Admin/Lapak/Listings/Show', [
            'listing' => LapakViews::listingForm($listing, $request->user()),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Detail produk Lapak',
                'Halaman staf Lapak AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function edit(Request $request, LapakListing $listing, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('update', $listing);

        return Inertia::render('Admin/Lapak/Listings/Form', [
            'listing' => LapakViews::listingForm($listing, $request->user()),
            'prefillLapakId' => null,
            'lapakOptions' => [],
            'categoryOptions' => $this->categoryOptions(includeInactive: true),
            'priceModeOptions' => LapakViews::priceModeOptions(),
            'availabilityOptions' => LapakViews::availabilityOptions(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Ubah produk Lapak',
                'Halaman staf Lapak AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function update(
        UpdateLapakListingRequest $request,
        LapakListing $listing,
        UpdateLapakListing $update,
    ): RedirectResponse {
        $update->handle(
            $listing,
            $request->payload(),
            $request->user(),
            $request->file('new_image'),
            $request->input('existing_image_asset_ids', []) ?: [],
            $request->input('remove_media_ids', []) ?: [],
        );

        return redirect()
            ->route('admin.lapak.listings.show', $listing)
            ->with('success', 'Data produk diperbarui.');
    }

    public function moderate(
        Request $request,
        LapakListing $listing,
        TransitionLapakListingModeration $transition,
    ): RedirectResponse {
        $this->authorize('moderate', $listing);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $next = LapakModerationStatus::tryFrom($validated['status']);
        if ($next === null) {
            return back()->withErrors(['status' => 'Status tidak dikenal.']);
        }

        $transition->handle($listing, $next, $request->user(), $validated['note'] ?? null);

        return redirect()
            ->route('admin.lapak.listings.show', $listing)
            ->with('success', 'Status produk diperbarui menjadi '.$next->label().'.');
    }

    public function availability(
        Request $request,
        LapakListing $listing,
        UpdateLapakListingAvailability $updateAvailability,
    ): RedirectResponse {
        $this->authorize('update', $listing);

        $validated = $request->validate([
            'availability' => ['required', 'string'],
        ]);

        $next = LapakListingAvailability::tryFrom($validated['availability']);
        if ($next === null) {
            return back()->withErrors(['availability' => 'Ketersediaan tidak dikenal.']);
        }

        $updateAvailability->handle($listing, $next, $request->user());

        return back()->with('success', 'Ketersediaan produk: '.$next->label().'.');
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function lapakOptions(): array
    {
        return Lapak::query()
            ->with('member')
            ->orderBy('name')
            ->get()
            ->map(fn (Lapak $lapak) => [
                'id' => $lapak->id,
                'label' => $lapak->name.' — '.($lapak->member?->legal_name ?? 'Anggota'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function categoryOptions(bool $includeInactive = false): array
    {
        return LapakCategory::query()
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (LapakCategory $category) => [
                'id' => $category->id,
                'label' => $category->is_active ? $category->name : $category->name.' (nonaktif)',
            ])
            ->values()
            ->all();
    }
}
