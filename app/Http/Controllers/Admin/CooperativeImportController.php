<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cooperative\MatchCooperativeImportRowRequest;
use App\Http\Requests\Cooperative\SelectCooperativeImportWorksheetRequest;
use App\Http\Requests\Cooperative\StoreCooperativeImportRequest;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportRow;
use App\Models\Member;
use App\Support\Cooperative\CooperativeImportViews;
use App\Support\Cooperative\CooperativeViews;
use App\Support\Cooperative\Import\ImportBatchService;
use App\Support\Cooperative\Import\ImportCommitter;
use App\Support\Cooperative\Import\ImportReverser;
use App\Support\Cooperative\Import\ManualRowReviewer;
use App\Support\PublicDelivery\PublicMetadataResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CooperativeImportController extends Controller
{
    public function index(Request $request, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('viewAny', CooperativeImportBatch::class);

        $batches = CooperativeImportBatch::query()
            ->with('creator')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CooperativeImportBatch $batch) => CooperativeImportViews::adminListItem($batch));

        return Inertia::render('Admin/Cooperative/Imports/Index', [
            'batches' => $batches,
            'canManage' => $request->user()->can('create', CooperativeImportBatch::class),
            'limits' => [
                'max_kilobytes' => (int) config('cooperative.imports_max_kilobytes', 10240),
                'max_rows' => (int) config('cooperative.imports_max_rows', 5000),
                'extensions' => ['csv', 'xlsx'],
            ],
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Import Data Lama',
                'Halaman staf impor data lama Koperasi. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function store(StoreCooperativeImportRequest $request, ImportBatchService $imports): RedirectResponse
    {
        $batch = $imports->upload($request->file('file'), $request->user());

        if ($batch->status === CooperativeImportBatchStatus::Failed) {
            return redirect()
                ->route('admin.cooperative.imports.show', $batch)
                ->with('error', $batch->summary_json['error'] ?? 'The spreadsheet could not be imported.');
        }

        $message = 'Berkas diunggah dan diurai. Pratinjau tidak mengubah data kanonik Koperasi/PSN.';
        if ($batch->needsSheetSelection()) {
            $message = 'Berkas diunggah. Pilih satu worksheet sumber. Pratinjau tidak mengubah data kanonik Koperasi/PSN.';
        } elseif ($batch->duplicate_of_batch_id !== null) {
            $message = 'Worksheet ini identik dengan impor #'.$batch->duplicate_of_batch_id.'. Tinjau sebelum commit. Pratinjau tidak mengubah data kanonik.';
        }

        return redirect()
            ->route('admin.cooperative.imports.show', $batch)
            ->with('success', $message);
    }

    public function selectWorksheet(
        SelectCooperativeImportWorksheetRequest $request,
        CooperativeImportBatch $batch,
        ImportBatchService $imports,
    ): RedirectResponse {
        try {
            $batch = $imports->selectWorksheet($batch, (string) $request->validated('worksheet_name'));
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.cooperative.imports.show', $batch)
                ->with('error', $exception->getMessage());
        }

        if ($batch->status === CooperativeImportBatchStatus::Failed) {
            return redirect()
                ->route('admin.cooperative.imports.show', $batch)
                ->with('error', $batch->summary_json['error'] ?? 'The selected worksheet could not be imported.');
        }

        $message = 'Worksheet dipilih dan diurai. Pratinjau tidak mengubah data kanonik Koperasi/PSN.';
        if ($batch->duplicate_of_batch_id !== null) {
            $message = 'Worksheet ini identik dengan impor #'.$batch->duplicate_of_batch_id.'. Tinjau sebelum commit. Pratinjau tidak mengubah data kanonik.';
        }

        return redirect()
            ->route('admin.cooperative.imports.show', $batch)
            ->with('success', $message);
    }

    public function show(Request $request, CooperativeImportBatch $batch, PublicMetadataResolver $metadata): Response
    {
        $this->authorize('view', $batch);

        $status = $request->string('status')->toString();
        $inspectId = $request->integer('inspect');

        $rows = $batch->rows()
            ->with(['matchedMember', 'matchedByUser'])
            ->when($status !== '', fn ($query) => $query->where('validation_status', $status))
            ->orderBy('source_row_number')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (CooperativeImportRow $row) => CooperativeImportViews::adminRowSummary($row));

        $inspected = null;
        if ($inspectId > 0) {
            $inspectRow = $batch->rows()
                ->with(['matchedMember', 'matchedByUser'])
                ->whereKey($inspectId)
                ->first();

            if ($inspectRow !== null) {
                $inspected = CooperativeImportViews::adminRowDetail($inspectRow);
            }
        }

        return Inertia::render('Admin/Cooperative/Imports/Show', [
            'batch' => CooperativeImportViews::adminDetail($batch->load('creator')),
            'rows' => $rows,
            'inspectedRow' => $inspected,
            'filters' => [
                'status' => $status,
                'inspect' => $inspectId > 0 ? $inspectId : null,
            ],
            'canManage' => $request->user()->can('update', $batch),
            'seo' => $metadata->forTransactionalPage(
                $request->path(),
                'Import Data Lama',
                'Halaman staf tinjauan impor Koperasi. Tidak untuk diindeks atau dibagikan.',
            )->toArray(),
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CooperativeImportBatch::class);

        $search = $request->string('search')->toString();

        $members = Member::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('legal_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('membership_number', 'like', '%'.$search.'%');
            })
            ->orderBy('legal_name')
            ->limit(20)
            ->get()
            ->map(fn (Member $member) => CooperativeViews::memberCandidate($member))
            ->values();

        return response()->json($members);
    }

    public function match(
        MatchCooperativeImportRowRequest $request,
        CooperativeImportBatch $batch,
        CooperativeImportRow $row,
        ManualRowReviewer $reviewer,
    ): RedirectResponse {
        abort_unless($row->batch_id === $batch->id, 404);

        $member = Member::query()->findOrFail((int) $request->validated('member_id'));
        $reviewer->assignMember($row, $member, $request->user(), $request->validated('review_notes'));

        return redirect()
            ->route('admin.cooperative.imports.show', [
                'batch' => $batch,
                'status' => $request->query('status'),
                'inspect' => $row->id,
            ])
            ->with('success', 'Pencocokan anggota disimpan.');
    }

    public function commit(Request $request, CooperativeImportBatch $batch, ImportCommitter $committer): RedirectResponse
    {
        $this->authorize('update', $batch);

        $validated = $request->validate([
            'confirm' => ['required', 'accepted'],
            'replace_legacy' => ['sometimes', 'boolean'],
            'replace_acknowledged' => ['sometimes', 'boolean'],
        ]);

        $replaceLegacy = (bool) ($validated['replace_legacy'] ?? false);
        $mode = $replaceLegacy
            ? CooperativeImportMode::ReplaceLegacy
            : CooperativeImportMode::Append;

        if ($mode === CooperativeImportMode::ReplaceLegacy && ! (bool) ($validated['replace_acknowledged'] ?? false)) {
            return redirect()
                ->route('admin.cooperative.imports.show', $batch)
                ->with('error', 'Replace mode requires explicit acknowledgement that prior legacy-import data will be replaced.');
        }

        try {
            $committer->commit(
                $batch,
                $request->user(),
                $mode,
                (bool) ($validated['replace_acknowledged'] ?? false),
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.cooperative.imports.show', $batch)
                ->with('error', $exception->getMessage());
        }

        $message = $mode === CooperativeImportMode::ReplaceLegacy
            ? 'Data import legacy sebelumnya diganti dan sumber baru dicatat. Tinjau peringatan yang tersisa.'
            : 'Baris siap telah dicatat ke data kanonik.';

        return redirect()
            ->route('admin.cooperative.imports.show', $batch)
            ->with('success', $message);
    }

    public function reverse(Request $request, CooperativeImportBatch $batch, ImportReverser $reverser): RedirectResponse
    {
        $this->authorize('update', $batch);

        try {
            $reverser->reverse($batch, $request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.cooperative.imports.show', $batch)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.cooperative.imports.show', $batch)
            ->with('success', 'Impor dibatalkan dengan jurnal penyesuaian. Baris staging tetap tercatat.');
    }
}
