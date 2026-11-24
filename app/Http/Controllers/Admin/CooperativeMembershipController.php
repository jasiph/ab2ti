<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Cooperative\AdjustPsnParticipation;
use App\Enums\PsnAdjustmentReason;
use App\Exceptions\Cooperative\PsnAdjustmentRejectedException;
use App\Exceptions\Cooperative\StalePsnConfirmationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cooperative\AdjustPsnParticipationRequest;
use App\Http\Requests\Cooperative\StoreCooperativeMembershipRequest;
use App\Http\Requests\Cooperative\UpdateCooperativeMembershipRequest;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Support\Cooperative\CooperativeViews;
use App\Support\Cooperative\PsnAdjustmentEligibility;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class CooperativeMembershipController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', CooperativeMembership::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $psn = $request->string('psn')->toString();
        $review = $request->string('review')->toString();

        $query = CooperativeMembership::query()
            ->with([
                'member',
                'psnParticipation' => fn ($participation) => $participation->withSum('entries as units_sum', 'units'),
            ]);

        CooperativeViews::applyAdminListFilters($query, $search, $status, $psn, $review);

        $memberships = $query
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CooperativeMembership $membership) => CooperativeViews::adminListItem($membership));

        return Inertia::render('Admin/Cooperative/Memberships/Index', [
            'memberships' => $memberships,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'psn' => $psn,
                'review' => $review,
            ],
            'canManage' => $request->user()->can('create', CooperativeMembership::class),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Anggota Koperasi',
                'Halaman staf Koperasi Pusat AB2TI. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function create(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('create', CooperativeMembership::class);

        $search = $request->string('search')->toString();

        $candidates = [];

        if ($search !== '') {
            $candidates = Member::query()
                ->whereDoesntHave('cooperativeMembership')
                ->where(function ($query) use ($search) {
                    $query->where('legal_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('membership_number', 'like', '%'.$search.'%');
                })
                ->orderBy('legal_name')
                ->limit(20)
                ->get()
                ->map(fn (Member $member) => CooperativeViews::memberCandidate($member))
                ->values()
                ->all();
        }

        return Inertia::render('Admin/Cooperative/Memberships/Create', [
            'candidates' => $candidates,
            'filters' => [
                'search' => $search,
            ],
            'statusValues' => CooperativeViews::statusValues(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Tetapkan keanggotaan Koperasi',
                'Halaman staf Koperasi Pusat AB2TI. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function store(StoreCooperativeMembershipRequest $request): RedirectResponse
    {
        $membership = new CooperativeMembership($request->membershipPayload());
        $membership->applyStatusSideEffects();
        $membership->save();

        return redirect()
            ->route('admin.cooperative.memberships.show', $membership)
            ->with('success', 'Keanggotaan Koperasi ditetapkan untuk anggota AB2TI yang dipilih.');
    }

    public function show(
        CooperativeMembership $membership,
        PublicMetadataResolver $metadata,
        Request $request,
    ): Response {
        $this->authorize('view', $membership);

        $canManage = $request->user()->can('update', $membership);
        $adjustmentBlock = PsnAdjustmentEligibility::ordinaryAdjustmentBlock($membership);

        return Inertia::render('Admin/Cooperative/Memberships/Show', [
            'membership' => CooperativeViews::adminDetail($membership),
            'canManage' => $canManage,
            'canAdjustPsn' => $canManage && $adjustmentBlock === null,
            'psnAdjustmentBlock' => $adjustmentBlock,
            'psnAdjustmentResult' => $request->session()->get('psn_adjustment_result'),
            'listQuery' => $request->only(['search', 'status', 'psn', 'review', 'page']),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Detail keanggotaan Koperasi',
                'Halaman staf Koperasi Pusat AB2TI. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function edit(
        Request $request,
        CooperativeMembership $membership,
        PublicMetadataResolver $metadata,
    ): Response {
        $this->authorize('update', $membership);

        return Inertia::render('Admin/Cooperative/Memberships/Edit', [
            'membership' => CooperativeViews::adminDetail($membership),
            'statusValues' => CooperativeViews::statusValues(),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Ubah keanggotaan Koperasi',
                'Halaman staf Koperasi Pusat AB2TI. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function update(
        UpdateCooperativeMembershipRequest $request,
        CooperativeMembership $membership,
    ): RedirectResponse {
        $membership->fill($request->membershipPayload());
        $membership->applyStatusSideEffects();
        $membership->save();

        return redirect()
            ->route('admin.cooperative.memberships.show', $membership)
            ->with('success', 'Keanggotaan Koperasi diperbarui.');
    }

    public function editPsn(
        Request $request,
        CooperativeMembership $membership,
        PublicMetadataResolver $metadata,
    ): Response|RedirectResponse {
        $this->authorize('update', $membership);

        $redirect = $this->redirectIfOrdinaryAdjustmentBlocked($membership);
        if ($redirect !== null) {
            return $redirect;
        }

        $detail = CooperativeViews::adminDetail($membership);

        return Inertia::render('Admin/Cooperative/Memberships/AdjustPsn', [
            'membership' => $detail,
            'reasons' => PsnAdjustmentReason::values(),
            'canManage' => true,
            'confirmationToken' => (string) Str::uuid(),
            'expectedCurrentUnits' => (int) ($detail['psn']['units'] ?? 0),
            'maxEffectiveDate' => now('Asia/Jakarta')->toDateString(),
            'stalePsn' => $request->session()->get('stale_psn'),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Kelola Saham PSN',
                'Halaman staf penyesuaian saham PSN. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function adjustPsn(
        AdjustPsnParticipationRequest $request,
        CooperativeMembership $membership,
        AdjustPsnParticipation $adjust,
    ): RedirectResponse {
        $redirect = $this->redirectIfOrdinaryAdjustmentBlocked($membership);
        if ($redirect !== null) {
            return $redirect;
        }

        try {
            $entry = $adjust->handle($membership, $request->user(), $request->adjustmentPayload());
        } catch (StalePsnConfirmationException $exception) {
            $this->logAdjustmentFailure($membership, $request, $exception);

            return redirect()
                ->route('admin.cooperative.memberships.psn.edit', $membership)
                ->with('error', $exception->getMessage())
                ->with('stale_psn', $exception->comparison());
        } catch (PsnAdjustmentRejectedException $exception) {
            $this->logAdjustmentFailure($membership, $request, $exception);

            if ($exception->rejectionCode === PsnAdjustmentRejectedException::NO_CHANGE) {
                return redirect()
                    ->route('admin.cooperative.memberships.psn.edit', $membership)
                    ->with('error', $exception->getMessage());
            }

            if (in_array($exception->rejectionCode, [
                PsnAdjustmentRejectedException::INACTIVE_MEMBERSHIP,
                PsnAdjustmentRejectedException::UNRESOLVED_PSN,
            ], true)) {
                return $this->redirectIfOrdinaryAdjustmentBlocked($membership)
                    ?? redirect()
                        ->route('admin.cooperative.memberships.show', $membership)
                        ->with('error', $exception->getMessage());
            }

            return redirect()
                ->route('admin.cooperative.memberships.psn.edit', $membership)
                ->with('error', $exception->getMessage());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->logAdjustmentFailure($membership, $request, $exception);

            return redirect()
                ->route('admin.cooperative.memberships.psn.edit', $membership)
                ->with('error', $exception->getMessage());
        }

        $provenance = $entry->provenance ?? [];

        return redirect()
            ->route('admin.cooperative.memberships.show', $membership)
            ->with('success', __('PSN share change recorded.'))
            ->with('psn_adjustment_result', [
                'previous_units' => (int) ($provenance['previous_units'] ?? 0),
                'delta_units' => (int) ($provenance['delta_units'] ?? $entry->units),
                'resulting_units' => (int) ($provenance['resulting_units'] ?? 0),
                'effective_date' => (string) ($provenance['effective_date'] ?? $entry->effective_date_start?->toDateString()),
                'reason' => (string) ($provenance['reason_code'] ?? ''),
                'origin' => (string) ($provenance['origin'] ?? 'administrative_adjustment'),
            ]);
    }

    private function redirectIfOrdinaryAdjustmentBlocked(CooperativeMembership $membership): ?RedirectResponse
    {
        $block = PsnAdjustmentEligibility::ordinaryAdjustmentBlock($membership);

        if ($block === 'unresolved') {
            $batchId = CooperativeViews::reviewIndicatorsForMember($membership->member)['reconciliation_batch_id'] ?? null;

            if ($batchId !== null) {
                return redirect()
                    ->route('admin.cooperative.imports.reconciliation.index', [
                        'batch' => $batchId,
                        'member' => $membership->member_id,
                        'psn_review' => 1,
                        'status' => 'pending',
                    ])
                    ->with('warning', __('PSN needs reconciliation before ordinary share adjustment.'));
            }

            return redirect()
                ->route('admin.cooperative.memberships.show', $membership)
                ->with('error', __('PSN needs reconciliation before ordinary share adjustment.'));
        }

        if ($block === 'inactive') {
            return redirect()
                ->route('admin.cooperative.memberships.show', $membership)
                ->with('error', __('Ordinary PSN share adjustment is unavailable while Cooperative membership is inactive.'));
        }

        return null;
    }

    private function logAdjustmentFailure(
        CooperativeMembership $membership,
        Request $request,
        \Throwable $exception,
    ): void {
        Log::notice('PSN administrative adjustment rejected', [
            'membership_id' => $membership->id,
            'operator_id' => $request->user()?->id,
            'exception' => $exception::class,
        ]);
    }
}
