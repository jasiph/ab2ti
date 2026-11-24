<?php

namespace App\Http\Controllers\Cooperative;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use App\Support\Cooperative\CooperativePublicStats;
use App\Support\PublicDelivery\PublicMetadataResolver;
use App\Support\Publishing\PublicationViews;
use Inertia\Inertia;
use Inertia\Response;

class PublicCooperativeController extends Controller
{
    public function index(
        PublicMetadataResolver $metadata,
        CooperativePublicStats $stats,
    ): Response {
        $updates = Publication::query()
            ->published()
            ->cooperativeUpdates()
            ->orderByDesc('published_at')
            ->limit(6)
            ->get(['id', 'type', 'title', 'slug', 'excerpt', 'published_at', 'status'])
            ->map(fn (Publication $publication) => PublicationViews::publicCard($publication))
            ->values()
            ->all();

        return Inertia::render('Frontend/Cooperative/Index', [
            'stats' => $stats->toArray(),
            'updates' => $updates,
            'contactUrl' => route('contact'),
            'membershipUrl' => route('membership.index'),
            'seo' => $metadata->forPublicInformationalPage(
                'koperasi',
                __('Cooperative'),
                __('Cooperative landing intro'),
            )->toArray(),
        ]);
    }
}
