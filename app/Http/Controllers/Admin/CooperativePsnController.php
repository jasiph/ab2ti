<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessParticipation;
use App\Models\CooperativeBusiness;
use App\Support\Cooperative\CooperativePsnViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CooperativePsnController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', CooperativeBusiness::class);

        $business = CooperativeBusiness::psn();
        $this->authorize('view', $business);

        $participations = BusinessParticipation::query()
            ->where('cooperative_business_id', $business->id)
            ->with(['membership.member', 'business'])
            ->withSum('entries as units_sum', 'units')
            ->get()
            ->filter(fn (BusinessParticipation $participation) => $participation->currentUnits() !== 0)
            ->sortBy(fn (BusinessParticipation $participation) => $participation->membership?->member?->legal_name)
            ->values()
            ->map(fn (BusinessParticipation $participation) => CooperativePsnViews::adminParticipant($participation));

        return Inertia::render('Admin/Cooperative/Psn/Index', [
            'summary' => CooperativePsnViews::adminSummary($business),
            'participants' => $participations,
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'PSN',
                'Halaman staf penyertaan PSN. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function show(Request $request, BusinessParticipation $participation, PublicMetadataResolver $metadata): Response
    {
        $participation->load(['business', 'membership.member', 'entries.importBatch']);
        $this->authorize('view', $participation->business);

        $participation->entries->each->setHidden([]);

        return Inertia::render('Admin/Cooperative/Psn/Show', [
            'summary' => CooperativePsnViews::adminSummary($participation->business),
            'participant' => CooperativePsnViews::adminParticipant($participation),
            'history' => $participation->entries
                ->map(fn ($entry) => CooperativePsnViews::adminHistoryItem($entry))
                ->values(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'PSN',
                'Halaman staf riwayat penyertaan PSN. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }
}
