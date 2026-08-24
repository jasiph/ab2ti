<?php

namespace App\Http\Controllers\Account;

use App\Actions\Lapak\CreateLapakListing;
use App\Actions\Lapak\SubmitLapakListingForReview;
use App\Actions\Lapak\UpdateLapakListing;
use App\Actions\Lapak\UpdateLapakListingAvailability;
use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lapak\OwnerStoreLapakListingRequest;
use App\Http\Requests\Lapak\OwnerUpdateLapakListingRequest;
use App\Models\Lapak;
use App\Models\LapakCategory;
use App\Models\LapakListing;
use App\Support\Lapak\LapakViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerLapakListingController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $lapak = $this->ownedLapak($request);

        return Inertia::render('Account/Lapak/Listings/Index', [
            'lapak' => LapakViews::ownerStorefront($lapak),
            'listings' => $lapak->listings()
                ->with('category')
                ->latest('updated_at')
                ->get()
                ->map(fn (LapakListing $listing) => LapakViews::ownerListing($listing))
                ->values(),
            'seo' => $this->seo($request, $metadata, 'Produk Lapak Saya'),
        ]);
    }

    public function create(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->ownedLapak($request);

        return Inertia::render('Account/Lapak/Listings/Form', [
            'listing' => null,
            'categoryOptions' => $this->activeCategories(),
            'priceModeOptions' => LapakViews::priceModeOptions(),
            'availabilityOptions' => LapakViews::availabilityOptions(),
            'seo' => $this->seo($request, $metadata, 'Tambah produk'),
        ]);
    }

    public function store(OwnerStoreLapakListingRequest $request, CreateLapakListing $create): RedirectResponse
    {
        $this->ownedLapak($request);

        $listing = $create->handle(
            $request->payload(),
            $request->user(),
            $request->file('new_image'),
            [],
        );

        return redirect()
            ->route('account.lapak.listings.index')
            ->with('success', 'Produk disimpan sebagai draf. Kirim untuk ditinjau jika sudah lengkap.');
    }

    public function edit(Request $request, LapakListing $listing, PublicMetadataResolver $metadata): Response
    {
        $this->assertOwnsListing($request, $listing);
        abort_unless($listing->moderation_status->ownerMayEditContent(), 403);

        return Inertia::render('Account/Lapak/Listings/Form', [
            'listing' => LapakViews::ownerListing($listing),
            'categoryOptions' => $this->activeCategories($listing->category_id),
            'priceModeOptions' => LapakViews::priceModeOptions(),
            'availabilityOptions' => LapakViews::availabilityOptions(),
            'seo' => $this->seo($request, $metadata, 'Ubah produk'),
        ]);
    }

    public function update(
        OwnerUpdateLapakListingRequest $request,
        LapakListing $listing,
        UpdateLapakListing $update,
        SubmitLapakListingForReview $submit,
    ): RedirectResponse {
        $this->assertOwnsListing($request, $listing);

        $updated = $update->handle(
            $listing,
            $request->payload(),
            $request->user(),
            $request->file('new_image'),
            [],
            $request->input('remove_media_ids', []) ?: [],
        );

        $wasPublished = $updated->moderation_status === LapakModerationStatus::Published;

        if ($wasPublished) {
            $submit->handle($updated, $request->user());
        }

        return redirect()
            ->route('account.lapak.listings.index')
            ->with('success', $wasPublished
                ? 'Perubahan disimpan dan dikirim ulang untuk ditinjau.'
                : 'Data produk disimpan.');
    }

    public function submit(Request $request, LapakListing $listing, SubmitLapakListingForReview $submit): RedirectResponse
    {
        $this->assertOwnsListing($request, $listing);
        abort_unless($listing->moderation_status->ownerMaySubmitForReview(), 403);

        $submit->handle($listing, $request->user());

        return redirect()
            ->route('account.lapak.listings.index')
            ->with('success', 'Produk dikirim untuk ditinjau staf.');
    }

    public function availability(
        Request $request,
        LapakListing $listing,
        UpdateLapakListingAvailability $updateAvailability,
    ): RedirectResponse {
        $this->assertOwnsListing($request, $listing);
        abort_unless($listing->moderation_status->ownerMayChangeAvailability(), 403);

        $validated = $request->validate([
            'availability' => ['required', 'string'],
        ]);

        $next = LapakListingAvailability::tryFrom($validated['availability']);
        abort_if($next === null, 422);

        $updateAvailability->handle($listing, $next, $request->user());

        return back()->with('success', 'Ketersediaan produk: '.$next->label().'.');
    }

    private function ownedLapak(Request $request): Lapak
    {
        $user = $request->user();
        abort_if($user->isPlatformStaff(), 403);

        $member = $user->linkedMember();
        abort_if($member === null || $member->lapak === null, 403);

        abort_unless($user->ownsLapak($member->lapak), 403);

        return $member->lapak;
    }

    private function assertOwnsListing(Request $request, LapakListing $listing): void
    {
        abort_unless($request->user()->ownsListing($listing), 403);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function activeCategories(?int $currentId = null): array
    {
        return LapakCategory::query()
            ->where(function ($query) use ($currentId) {
                $query->where('is_active', true);
                if ($currentId) {
                    $query->orWhere('id', $currentId);
                }
            })
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

    /**
     * @return array<string, mixed>
     */
    private function seo(Request $request, PublicMetadataResolver $metadata, string $title): array
    {
        return $metadata->forTransactionalPage(
            $request->path(),
            $title,
            'Ruang Lapak anggota. Tidak untuk diindeks.',
        )->toArray();
    }
}
