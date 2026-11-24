<?php

namespace App\Http\Controllers\Account;

use App\Actions\Lapak\CreateLapak;
use App\Actions\Lapak\SubmitLapakForReview;
use App\Actions\Lapak\UpdateLapak;
use App\Enums\LapakListingAvailability;
use App\Enums\LapakModerationStatus;
use App\Exceptions\UniqueMemberLapakException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lapak\OwnerStoreLapakRequest;
use App\Http\Requests\Lapak\OwnerUpdateLapakRequest;
use App\Models\Lapak;
use App\Models\Member;
use App\Support\Cooperative\CooperativeViews;
use App\Support\Lapak\LapakViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerLapakController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $member = $this->linkedMember($request);

        if ($member === null) {
            return Inertia::render('Account/Lapak/Unlinked', [
                'seo' => $this->seo($request, $metadata, 'Lapak Saya'),
            ]);
        }

        $lapak = $member->lapak;

        if ($lapak === null) {
            $member->loadMissing('cooperativeMembership');

            return Inertia::render('Account/Lapak/Empty', [
                'member' => [
                    'legal_name' => $member->legal_name,
                    'membership_number' => $member->membership_number,
                ],
                'cooperative' => CooperativeViews::workspaceStatus($member),
                'seo' => $this->seo($request, $metadata, 'Buat Lapak Saya'),
            ]);
        }

        $member->loadMissing('cooperativeMembership');
        $lapak->load('listings');

        return Inertia::render('Account/Lapak/Overview', [
            'member' => [
                'legal_name' => $member->legal_name,
                'membership_number' => $member->membership_number,
            ],
            'cooperative' => CooperativeViews::workspaceStatus($member),
            'lapak' => LapakViews::ownerStorefront($lapak),
            'listing_counts' => [
                'total' => $lapak->listings->count(),
                'available' => $lapak->listings
                    ->filter(fn ($item) => $item->availability === LapakListingAvailability::Available)
                    ->count(),
                'pending' => $lapak->listings
                    ->filter(fn ($item) => $item->moderation_status === LapakModerationStatus::PendingReview)
                    ->count(),
                'rejected' => $lapak->listings
                    ->filter(fn ($item) => $item->moderation_status === LapakModerationStatus::Rejected)
                    ->count(),
            ],
            'listings' => $lapak->listings
                ->map(fn ($listing) => LapakViews::ownerListing($listing))
                ->values(),
            'seo' => $this->seo($request, $metadata, 'Lapak Saya'),
        ]);
    }

    public function create(Request $request, PublicMetadataResolver $metadata): Response
    {
        $member = $this->requireLinkedMember($request);

        abort_if($member->lapak !== null, 403);

        return Inertia::render('Account/Lapak/Form', [
            'lapak' => null,
            'seo' => $this->seo($request, $metadata, 'Buat Lapak Saya'),
        ]);
    }

    public function store(OwnerStoreLapakRequest $request, CreateLapak $create): RedirectResponse
    {
        try {
            $create->handle(
                $request->payload(),
                $request->user(),
                $request->file('new_cover'),
                null,
            );
        } catch (UniqueMemberLapakException $exception) {
            return redirect()
                ->route('account.lapak.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('account.lapak.index')
            ->with('success', 'Lapak dibuat sebagai draf. Lengkapi data, lalu kirim untuk ditinjau.');
    }

    public function edit(Request $request, Lapak $lapak, PublicMetadataResolver $metadata): Response
    {
        $this->assertOwns($request, $lapak);
        abort_unless($lapak->moderation_status->ownerMayEditContent(), 403);

        return Inertia::render('Account/Lapak/Form', [
            'lapak' => LapakViews::ownerStorefront($lapak),
            'seo' => $this->seo($request, $metadata, 'Ubah Lapak Saya'),
        ]);
    }

    public function update(
        OwnerUpdateLapakRequest $request,
        Lapak $lapak,
        UpdateLapak $update,
        SubmitLapakForReview $submit,
    ): RedirectResponse {
        $this->assertOwns($request, $lapak);

        $updated = $update->handle(
            $lapak,
            $request->payload(),
            $request->user(),
            $request->file('new_cover'),
            null,
            (bool) $request->boolean('remove_cover'),
        );

        $wasPublished = $updated->moderation_status === LapakModerationStatus::Published;

        if ($wasPublished) {
            $submit->handle($updated, $request->user());
        }

        return redirect()
            ->route('account.lapak.index')
            ->with('success', $wasPublished
                ? 'Perubahan disimpan dan dikirim ulang untuk ditinjau. Lapak tidak tayang sampai staf menyetujui.'
                : 'Data Lapak disimpan.');
    }

    public function submit(Request $request, Lapak $lapak, SubmitLapakForReview $submit): RedirectResponse
    {
        $this->assertOwns($request, $lapak);
        abort_unless($lapak->moderation_status->ownerMaySubmitForReview(), 403);

        $submit->handle($lapak, $request->user());

        return redirect()
            ->route('account.lapak.index')
            ->with('success', 'Lapak dikirim untuk ditinjau staf.');
    }

    private function linkedMember(Request $request): ?Member
    {
        $user = $request->user();
        abort_if($user->isPlatformStaff(), 403);

        return $user->linkedMember();
    }

    private function requireLinkedMember(Request $request): Member
    {
        $member = $this->linkedMember($request);
        abort_if($member === null, 403);

        return $member;
    }

    private function assertOwns(Request $request, Lapak $lapak): void
    {
        abort_unless($request->user()->ownsLapak($lapak), 403);
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
