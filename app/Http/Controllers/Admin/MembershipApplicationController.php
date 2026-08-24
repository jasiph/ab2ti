<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Membership\ApproveMembershipApplication;
use App\Actions\Membership\RejectMembershipApplication;
use App\Actions\Membership\RequestMembershipApplicationRevision;
use App\Actions\Membership\StartMembershipApplicationReview;
use App\Enums\MembershipApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\RejectMembershipApplicationRequest;
use App\Http\Requests\Membership\RequestMembershipApplicationRevisionRequest;
use App\Http\Requests\Membership\StartMembershipApplicationReviewRequest;
use App\Models\MembershipApplication;
use App\Support\Membership\MembershipViews;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipApplicationController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', MembershipApplication::class);

        $status = $request->string('status')->toString();
        $search = $request->string('search')->toString();

        $applications = MembershipApplication::query()
            ->when(
                $status !== '' && MembershipApplicationStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('legal_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('reference_code', 'like', '%'.$search.'%');
                });
            })
            ->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'under_review' THEN 1 WHEN 'revision_requested' THEN 2 ELSE 3 END")
            ->orderByDesc('submitted_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MembershipApplication $application) => MembershipViews::applicationListItem($application));

        return Inertia::render('Admin/Membership/Applications/Index', [
            'applications' => $applications,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => array_map(
                fn (MembershipApplicationStatus $item) => $item->value,
                MembershipApplicationStatus::cases(),
            ),
            'seo' => $this->privateSeo($metadata, $request->path(), 'Antrian permohonan keanggotaan'),
        ]);
    }

    public function show(
        Request $request,
        MembershipApplication $application,
        PublicMetadataResolver $metadata,
    ): Response {
        $this->authorize('view', $application);

        return Inertia::render('Admin/Membership/Applications/Show', [
            'application' => MembershipViews::applicationDetail($application, $request->user()),
            'canViewDocuments' => $request->user()->can('membership-documents.view'),
            'revisionCapabilityUrl' => $request->session()->get('revision_capability_url'),
            'seo' => $this->privateSeo($metadata, $request->path(), 'Detail permohonan keanggotaan'),
        ]);
    }

    public function review(
        StartMembershipApplicationReviewRequest $request,
        MembershipApplication $application,
        StartMembershipApplicationReview $startReview,
    ): RedirectResponse {
        $startReview->handle(
            $application,
            $request->user(),
            $request->validated('staff_notes'),
        );

        return redirect()
            ->route('admin.membership.applications.show', $application)
            ->with('success', 'Permohonan mulai ditinjau.');
    }

    public function approve(
        Request $request,
        MembershipApplication $application,
        ApproveMembershipApplication $approve,
    ): RedirectResponse {
        $this->authorize('approve', $application);

        $member = $approve->handle($application, $request->user());

        return redirect()
            ->route('admin.membership.members.show', $member)
            ->with('success', 'Permohonan disetujui. Anggota AB2TI telah dicatat.');
    }

    public function reject(
        RejectMembershipApplicationRequest $request,
        MembershipApplication $application,
        RejectMembershipApplication $reject,
    ): RedirectResponse {
        $reject->handle(
            $application,
            $request->user(),
            $request->validated('rejection_reason'),
        );

        return redirect()
            ->route('admin.membership.applications.show', $application)
            ->with('success', 'Permohonan ditolak.');
    }

    public function requestRevision(
        RequestMembershipApplicationRevisionRequest $request,
        MembershipApplication $application,
        RequestMembershipApplicationRevision $requestRevision,
    ): RedirectResponse {
        $issued = $requestRevision->handle(
            $application,
            $request->user(),
            $request->validated('revision_reason'),
        );

        $url = route('capability.redeem', ['secret' => $issued->plainSecret]);

        return redirect()
            ->route('admin.membership.applications.show', $application)
            ->with('success', 'Revisi diminta. Salin tautan sekarang; tautan tidak disimpan di server.')
            ->with('revision_capability_url', $url);
    }

    /**
     * @return array<string, mixed>
     */
    private function privateSeo(PublicMetadataResolver $metadata, string $path, string $title): array
    {
        return $metadata->forTransactionalPage(
            $path,
            $title,
            'Halaman staf keanggotaan AB2TI. Tidak untuk diindeks atau dibagikan.',
        )->toArray();
    }
}
