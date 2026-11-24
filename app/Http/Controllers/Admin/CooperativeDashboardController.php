<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CooperativeMembership;
use App\Support\Cooperative\CooperativeAdminDashboard;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CooperativeDashboardController extends Controller
{
    public function index(
        Request $request,
        CooperativeAdminDashboard $dashboard,
        PublicMetadataResolver $metadata,
    ): Response {
        $this->authorize('viewAny', CooperativeMembership::class);

        return Inertia::render('Admin/Cooperative/Dashboard', [
            'dashboard' => $dashboard->toArray(),
            'canManage' => $request->user()->can('create', CooperativeMembership::class),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Dashboard Koperasi',
                'Halaman staf operasional Koperasi Pusat AB2TI. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }
}
