<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewOutcome;
use App\Enums\CooperativeImportReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cooperative\ResolveImportPsnReviewRequest;
use App\Http\Requests\Cooperative\ResolveImportReviewItemRequest;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportReviewItem;
use App\Models\CooperativeImportRow;
use App\Support\Cooperative\CooperativeReconciliationViews;
use App\Support\Cooperative\Import\ImportReviewInventory;
use App\Support\Cooperative\Import\ImportReviewResolver;
use App\Support\Cooperative\Import\PsnManualReconciler;
use App\Support\Cooperative\Import\PsnReconciliationReadiness;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CooperativeImportReconciliationController extends Controller
{
    public function index(
        Request $request,
        CooperativeImportBatch $batch,
        ImportReviewInventory $inventory,
        PsnReconciliationReadiness $psnReadiness,
        PublicMetadataResolver $metadata,
    ): Response {
        $this->authorize('view', $batch);

        abort_unless($batch->isCommitted(), 404);

        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();
        $sourceRow = $request->integer('source_row');
        $memberId = $request->integer('member');
        $inspectRowId = $request->integer('inspect');
        $psnOnly = $request->boolean('psn_review');
        $profileOnly = $request->boolean('profile_warning');
        $duplicateOnly = $request->boolean('duplicate_warning');

        $itemsQuery = CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->with(['importRow.matchedMember', 'importRow.createdMember', 'resolvedByUser'])
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($status === 'pending', fn ($query) => $query->where('status', CooperativeImportReviewStatus::Pending))
            ->when($status === 'resolved', fn ($query) => $query->whereIn('status', [
                CooperativeImportReviewStatus::Resolved,
                CooperativeImportReviewStatus::AcceptedAsIs,
            ]))
            ->when($sourceRow > 0, function ($query) use ($batch, $sourceRow) {
                $query->whereHas('importRow', fn ($rowQuery) => $rowQuery
                    ->where('batch_id', $batch->id)
                    ->where('source_row_number', $sourceRow));
            })
            ->when($memberId > 0, function ($query) use ($memberId) {
                $query->whereHas('importRow', fn ($rowQuery) => $rowQuery
                    ->where(fn ($memberQuery) => $memberQuery
                        ->where('matched_member_id', $memberId)
                        ->orWhere('created_member_id', $memberId)));
            })
            ->when($psnOnly, fn ($query) => $query->where('category', CooperativeImportReviewCategory::PsnUnconfirmed))
            ->when($profileOnly, fn ($query) => $query->where('category', CooperativeImportReviewCategory::ProfileIncomplete))
            ->when($duplicateOnly, fn ($query) => $query->whereIn('category', [
                CooperativeImportReviewCategory::SharedEmail,
                CooperativeImportReviewCategory::SharedPhone,
                CooperativeImportReviewCategory::DuplicateNik,
                CooperativeImportReviewCategory::DuplicatePerson,
            ]))
            ->orderBy('import_row_id')
            ->orderBy('category');

        $items = $itemsQuery
            ->paginate(30)
            ->withQueryString()
            ->through(fn (CooperativeImportReviewItem $item) => CooperativeReconciliationViews::listItem($item));

        $inspectedPsn = null;
        if ($inspectRowId > 0) {
            $inspectRow = $batch->rows()
                ->with(['matchedMember', 'createdMember', 'matchedByUser'])
                ->whereKey($inspectRowId)
                ->first();

            if ($inspectRow !== null) {
                $inspectedPsn = CooperativeReconciliationViews::psnInspect($inspectRow);
            }
        }

        return Inertia::render('Admin/Cooperative/Imports/Reconciliation', [
            ...CooperativeReconciliationViews::dashboard($batch, $inventory, $psnReadiness),
            'items' => $items,
            'inspectedPsn' => $inspectedPsn,
            'filters' => [
                'category' => $category,
                'status' => $status,
                'source_row' => $sourceRow > 0 ? $sourceRow : null,
                'member' => $memberId > 0 ? $memberId : null,
                'inspect' => $inspectRowId > 0 ? $inspectRowId : null,
                'psn_review' => $psnOnly,
                'profile_warning' => $profileOnly,
                'duplicate_warning' => $duplicateOnly,
            ],
            'canManage' => $request->user()->can('update', $batch),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Perlu Validasi',
                'Halaman staf rekonsiliasi pasca-impor Koperasi. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function resolveItem(
        ResolveImportReviewItemRequest $request,
        CooperativeImportBatch $batch,
        CooperativeImportReviewItem $item,
        ImportReviewResolver $resolver,
    ): RedirectResponse {
        abort_unless($item->batch_id === $batch->id, 404);

        $outcome = CooperativeImportReviewOutcome::from($request->validated('outcome'));

        if (in_array($outcome, [
            CooperativeImportReviewOutcome::ManualReconciliation,
            CooperativeImportReviewOutcome::ConfirmedNoPsnParticipation,
        ], true)) {
            return redirect()
                ->route('admin.cooperative.imports.reconciliation.index', [
                    'batch' => $batch,
                    'inspect' => $item->import_row_id,
                ])
                ->with('error', 'Use the PSN reconciliation action for this review item.');
        }

        try {
            $resolver->resolve($item, $request->user(), $outcome, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Keputusan validasi disimpan.');
    }

    public function resolvePsn(
        ResolveImportPsnReviewRequest $request,
        CooperativeImportBatch $batch,
        CooperativeImportRow $row,
        PsnManualReconciler $reconciler,
    ): RedirectResponse {
        abort_unless($row->batch_id === $batch->id, 404);

        $outcome = CooperativeImportReviewOutcome::from($request->validated('outcome'));

        try {
            $reconciler->resolve($batch, $row, $request->user(), $outcome, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.cooperative.imports.reconciliation.index', [
                'batch' => $batch,
                'category' => CooperativeImportReviewCategory::PsnUnconfirmed->value,
            ])
            ->with('success', 'Rekonsiliasi PSN disimpan.');
    }
}
