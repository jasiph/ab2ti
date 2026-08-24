<?php

namespace App\Http\Controllers\Admin\Lapak;

use App\Actions\Lapak\CreateLapak;
use App\Actions\Lapak\TransitionLapakModeration;
use App\Actions\Lapak\UpdateLapak;
use App\Enums\LapakModerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lapak\StoreLapakRequest;
use App\Http\Requests\Lapak\UpdateLapakRequest;
use App\Models\Lapak;
use App\Models\Member;
use App\Support\Lapak\LapakViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LapakController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', Lapak::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $lapaks = Lapak::query()
            ->with('member')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('member', function ($member) use ($search) {
                            $member->where('legal_name', 'like', '%'.$search.'%')
                                ->orWhere('membership_number', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                $status !== '' && LapakModerationStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('moderation_status', $status),
            )
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Lapak $lapak) => LapakViews::storefrontListItem($lapak));

        return Inertia::render('Admin/Lapak/Storefronts/Index', [
            'lapaks' => $lapaks,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => LapakViews::moderationOptions(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Daftar Lapak Anggota',
                'Halaman staf Lapak AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function create(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('create', Lapak::class);

        return Inertia::render('Admin/Lapak/Storefronts/Form', [
            'lapak' => null,
            'memberOptions' => $this->memberOptions(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Buat Lapak Anggota',
                'Halaman staf Lapak AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function store(StoreLapakRequest $request, CreateLapak $create): RedirectResponse
    {
        $lapak = $create->handle(
            $request->payload(),
            $request->user(),
            $request->file('new_cover'),
            $request->integer('existing_cover_asset_id') ?: null,
        );

        return redirect()
            ->route('admin.lapak.storefronts.show', $lapak)
            ->with('success', 'Lapak dibuat sebagai draf.');
    }

    public function show(Request $request, Lapak $lapak, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('view', $lapak);

        $lapak->load(['member', 'listings.category']);

        return Inertia::render('Admin/Lapak/Storefronts/Show', [
            'lapak' => LapakViews::storefrontForm($lapak, $request->user()),
            'listings' => $lapak->listings
                ->map(fn ($listing) => LapakViews::listingListItem($listing))
                ->values(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Detail Lapak Anggota',
                'Halaman staf Lapak AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function edit(Request $request, Lapak $lapak, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('update', $lapak);

        return Inertia::render('Admin/Lapak/Storefronts/Form', [
            'lapak' => LapakViews::storefrontForm($lapak, $request->user()),
            'memberOptions' => [],
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Ubah Lapak Anggota',
                'Halaman staf Lapak AB2TI. Tidak untuk diindeks.',
            )->toArray(),
        ]);
    }

    public function update(UpdateLapakRequest $request, Lapak $lapak, UpdateLapak $update): RedirectResponse
    {
        $update->handle(
            $lapak,
            $request->payload(),
            $request->user(),
            $request->file('new_cover'),
            $request->integer('existing_cover_asset_id') ?: null,
            (bool) $request->boolean('remove_cover'),
        );

        return redirect()
            ->route('admin.lapak.storefronts.show', $lapak)
            ->with('success', 'Data Lapak diperbarui.');
    }

    public function moderate(
        Request $request,
        Lapak $lapak,
        TransitionLapakModeration $transition,
    ): RedirectResponse {
        $this->authorize('moderate', $lapak);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $next = LapakModerationStatus::tryFrom($validated['status']);
        if ($next === null) {
            return back()->withErrors(['status' => 'Status tidak dikenal.']);
        }

        $transition->handle($lapak, $next, $request->user(), $validated['note'] ?? null);

        return redirect()
            ->route('admin.lapak.storefronts.show', $lapak)
            ->with('success', 'Status Lapak diperbarui menjadi '.$next->label().'.');
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function memberOptions(): array
    {
        return Member::query()
            ->whereDoesntHave('lapak')
            ->orderBy('legal_name')
            ->limit(200)
            ->get(['id', 'legal_name', 'membership_number'])
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'label' => $member->legal_name.' ('.$member->membership_number.')',
            ])
            ->values()
            ->all();
    }
}
