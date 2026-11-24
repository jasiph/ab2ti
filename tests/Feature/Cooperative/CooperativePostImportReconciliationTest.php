<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeImportMode;
use App\Enums\CooperativeImportReviewCategory;
use App\Enums\CooperativeImportReviewOutcome;
use App\Enums\CooperativeImportReviewStatus;
use App\Enums\ParticipationUnitsOrigin;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportReviewAction;
use App\Models\CooperativeImportReviewItem;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Models\User;
use App\Support\Cooperative\CooperativePublicStats;
use App\Support\Cooperative\Import\ImportCommitter;
use App\Support\Cooperative\Import\ImportReviewSynchronizer;
use App\Support\Cooperative\Import\PsnReconciliationReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithAcl;
use Tests\Support\CooperativeLegacyFixture;
use Tests\TestCase;

class CooperativePostImportReconciliationTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_warning_resolution_preserves_provenance(): void
    {
        [$batch, $staff, $item] = $this->committedBatchWithProfileWarning();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolveItem', [
            'batch' => $batch,
            'item' => $item,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::AcceptedAsIs->value,
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(CooperativeImportReviewStatus::AcceptedAsIs, $item->status);
        $this->assertDatabaseHas('cooperative_import_review_items', [
            'id' => $item->id,
            'warning_code' => 'missing_email',
            'status' => CooperativeImportReviewStatus::AcceptedAsIs->value,
        ]);
        $this->assertDatabaseCount('cooperative_import_review_actions', 1);
        $this->assertSame('missing_email', CooperativeImportReviewAction::query()->first()->warning_code);
    }

    public function test_missing_email_can_be_accepted_as_is(): void
    {
        [$batch, $staff, $item] = $this->committedBatchWithProfileWarning();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolveItem', [
            'batch' => $batch,
            'item' => $item,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::AcceptedAsIs->value,
        ]);

        $member = Member::query()->firstOrFail();
        $this->assertNull($member->email);
    }

    public function test_profile_correction_updates_member(): void
    {
        [$batch, $staff, $item] = $this->committedBatchWithProfileWarning();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolveItem', [
            'batch' => $batch,
            'item' => $item,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::Corrected->value,
            'email' => 'corrected@example.test',
        ]);

        $this->assertSame('corrected@example.test', Member::query()->firstOrFail()->email);
        $this->assertSame(CooperativeImportReviewOutcome::Corrected, $item->fresh()->outcome);
    }

    public function test_shared_email_can_be_marked_confirmed_distinct(): void
    {
        [$batch, $staff] = $this->committedBatchWithSharedEmail();
        $item = CooperativeImportReviewItem::query()
            ->where('category', CooperativeImportReviewCategory::SharedEmail)
            ->firstOrFail();

        $before = Member::query()->count();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolveItem', [
            'batch' => $batch,
            'item' => $item,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::ConfirmedDistinct->value,
        ]);

        $this->assertSame($before, Member::query()->count());
        $this->assertSame(CooperativeImportReviewOutcome::ConfirmedDistinct, $item->fresh()->outcome);
    }

    public function test_shared_phone_does_not_merge_members(): void
    {
        [$batch, $staff] = $this->committedBatchWithSharedPhone();
        $item = CooperativeImportReviewItem::query()
            ->where('category', CooperativeImportReviewCategory::SharedPhone)
            ->firstOrFail();

        $before = Member::query()->count();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolveItem', [
            'batch' => $batch,
            'item' => $item,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::AcceptedSharedContact->value,
        ]);

        $this->assertSame($before, Member::query()->count());
    }

    public function test_duplicate_nik_review_does_not_merge_or_delete(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([
            [
                'Nama' => 'Duplikat A',
                'NIK' => '3174010101900001',
                'Email' => 'dup.a@example.test',
                'Telepon' => '081200000301',
                'Total' => '1.000.000',
                'No. Saham' => '33-001001',
            ],
            [
                'Nama' => 'Duplikat B',
                'NIK' => '3174010101900001',
                'Email' => 'dup.b@example.test',
                'Telepon' => '081200000302',
                'Total' => '1.000.000',
                'No. Saham' => '33-001002',
            ],
        ]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('dup-nik.csv', $csv),
        ]);

        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit(
            $batch,
            $staff,
            CooperativeImportMode::ReplaceLegacy,
            true,
        );

        $this->assertSame(2, Member::query()->count());
        app(ImportReviewSynchronizer::class)->sync($batch->fresh());
        $item = CooperativeImportReviewItem::query()
            ->where('category', CooperativeImportReviewCategory::DuplicateNik)
            ->firstOrFail();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolveItem', [
            'batch' => $batch,
            'item' => $item,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::ConfirmedSamePerson->value,
        ]);

        $this->assertSame(2, Member::query()->count());
        $this->assertSame(CooperativeImportReviewOutcome::ConfirmedSamePerson, $item->fresh()->outcome);
    }

    public function test_manual_psn_resolution_creates_only_psn_component(): void
    {
        [$batch, $staff, $row] = $this->committedBatchWithPsnReview();

        $memberBefore = Member::query()->count();
        $fundsBefore = \App\Models\CooperativeFundEntry::query()->count();
        $membershipsBefore = \App\Models\CooperativeMembership::query()->count();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolvePsn', [
            'batch' => $batch,
            'row' => $row,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::ManualReconciliation->value,
            'units' => 2,
            'share_reference' => '33-001101',
        ]);

        $this->assertSame($memberBefore, Member::query()->count());
        $this->assertSame($fundsBefore, \App\Models\CooperativeFundEntry::query()->count());
        $this->assertSame($membershipsBefore, \App\Models\CooperativeMembership::query()->count());
        $this->assertSame(1, ParticipationEntry::query()->count());
        $this->assertNotNull($row->fresh()->psn_committed_at);
    }

    public function test_psn_manual_units_are_integer_and_auditable(): void
    {
        [$batch, $staff, $row] = $this->committedBatchWithPsnReview();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolvePsn', [
            'batch' => $batch,
            'row' => $row,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::ManualReconciliation->value,
            'units' => 3,
        ]);

        $entry = ParticipationEntry::query()->firstOrFail();
        $this->assertSame(3, $entry->units);
        $this->assertSame(ParticipationUnitsOrigin::ManualReconciliation, $entry->units_origin);
        $this->assertSame('manual_reconciliation', $entry->provenance['reconciliation'] ?? null);
        $this->assertDatabaseHas('cooperative_import_review_actions', [
            'import_row_id' => $row->id,
            'action' => 'psn_reconciliation',
        ]);
    }

    public function test_second_psn_resolution_does_not_duplicate_opening_entry(): void
    {
        [$batch, $staff, $row] = $this->committedBatchWithPsnReview();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolvePsn', [
            'batch' => $batch,
            'row' => $row,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::ManualReconciliation->value,
            'units' => 1,
        ])->assertRedirect();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolvePsn', [
            'batch' => $batch,
            'row' => $row->fresh(),
        ]), [
            'outcome' => CooperativeImportReviewOutcome::ManualReconciliation->value,
            'units' => 2,
        ])->assertSessionHas('error');

        $this->assertSame(1, ParticipationEntry::query()->count());
    }

    public function test_confirmed_no_participation_resolves_without_fake_units(): void
    {
        [$batch, $staff, $row] = $this->committedBatchWithPsnReview();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolvePsn', [
            'batch' => $batch,
            'row' => $row,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::ConfirmedNoPsnParticipation->value,
            'administrative_note' => 'Verified no PSN units in source.',
        ]);

        $this->assertDatabaseCount('participation_entries', 0);
        $this->assertNotNull($row->fresh()->psn_committed_at);
    }

    public function test_incomplete_psn_reconciliation_keeps_public_totals_hidden(): void
    {
        [$batch] = $this->committedBatchWithPsnReview();

        $stats = (new CooperativePublicStats)->toArray();
        $this->assertFalse($stats['psn_legacy_reconciliation_complete']);
        $this->assertNull($stats['total_psn_shares']);

        $readiness = (new PsnReconciliationReadiness)->assess($batch);
        $this->assertFalse($readiness['complete']);
        $this->assertFalse($readiness['ready_for_publication']);
    }

    public function test_complete_psn_disposition_can_be_reported_ready_for_publication(): void
    {
        [$batch, $staff, $row] = $this->committedBatchWithPsnReview();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolvePsn', [
            'batch' => $batch,
            'row' => $row,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::ManualReconciliation->value,
            'units' => 1,
        ]);

        $readiness = (new PsnReconciliationReadiness)->assess($batch->fresh());
        $this->assertTrue($readiness['complete']);
    }

    public function test_ab2ti_registry_complete_remains_unaffected(): void
    {
        [$batch, $staff, $item] = $this->committedBatchWithProfileWarning();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolveItem', [
            'batch' => $batch,
            'item' => $item,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::AcceptedAsIs->value,
        ]);

        $stats = (new CooperativePublicStats)->toArray();
        $this->assertFalse($stats['member_registry_complete']);
        $this->assertNull($stats['total_ab2ti_members']);
    }

    public function test_nonstaff_cannot_access_reconciliation(): void
    {
        [$batch] = $this->committedBatchWithProfileWarning();
        $memberUser = User::factory()->create();

        $this->actingAs($memberUser)
            ->get(route('admin.cooperative.imports.reconciliation.index', $batch))
            ->assertForbidden();
    }

    public function test_view_only_staff_cannot_resolve_items(): void
    {
        [$batch, , $item] = $this->committedBatchWithProfileWarning();
        $viewer = $this->userWithPermissions(['cooperative.view']);

        $this->actingAs($viewer)->post(route('admin.cooperative.imports.reconciliation.resolveItem', [
            'batch' => $batch,
            'item' => $item,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::AcceptedAsIs->value,
        ])->assertForbidden();
    }

    public function test_reconciliation_dashboard_shows_unique_and_total_counts(): void
    {
        [$batch, $staff] = $this->committedBatchWithProfileWarning();
        app(ImportReviewSynchronizer::class)->sync($batch);

        $this->actingAs($staff)
            ->get(route('admin.cooperative.imports.reconciliation.index', $batch))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Cooperative/Imports/Reconciliation')
                ->has('summary.unique_rows_with_review')
                ->has('summary.total_review_flags'));
    }

    public function test_psn_warning_codes_do_not_create_other_legacy_duplicates(): void
    {
        [$batch, , $row] = $this->committedBatchWithPsnReview();

        $this->assertSame(0, CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->where('category', CooperativeImportReviewCategory::OtherLegacy)
            ->count());

        $psnItems = CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->where('category', CooperativeImportReviewCategory::PsnUnconfirmed)
            ->get();

        $this->assertGreaterThan(0, $psnItems->count());
        $this->assertTrue($psnItems->every(
            fn (CooperativeImportReviewItem $item) => in_array($item->warning_code, CooperativeImportReviewCategory::psnShareCodes(), true)
        ));
        $this->assertContains('amount_not_divisible_by_unit_value', $row->validation_errors ?? []);
    }

    public function test_one_warning_produces_one_canonical_reconciliation_item(): void
    {
        [$batch] = $this->committedBatchWithPsnReview();

        $pairs = CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->get()
            ->map(fn (CooperativeImportReviewItem $item) => $item->import_row_id.'|'.$item->category->value.'|'.$item->warning_code);

        $this->assertSame($pairs->count(), $pairs->unique()->count());
    }

    public function test_review_sync_is_idempotent(): void
    {
        [$batch] = $this->committedBatchWithPsnReview();
        $sync = app(ImportReviewSynchronizer::class);

        $sync->sync($batch);
        $first = CooperativeImportReviewItem::query()->where('batch_id', $batch->id)->count();
        $sync->sync($batch);
        $second = CooperativeImportReviewItem::query()->where('batch_id', $batch->id)->count();
        $sync->sync($batch);
        $third = CooperativeImportReviewItem::query()->where('batch_id', $batch->id)->count();

        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
        $this->assertGreaterThan(0, $first);
    }

    public function test_psn_row_with_two_codes_is_one_problem_with_multiple_reasons(): void
    {
        [$batch, $staff, $row] = $this->committedBatchWithPsnReview();

        $psnFlags = CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->where('import_row_id', $row->id)
            ->where('category', CooperativeImportReviewCategory::PsnUnconfirmed)
            ->get();

        $this->assertGreaterThanOrEqual(2, $psnFlags->count());
        $this->assertSame(1, $psnFlags->pluck('import_row_id')->unique()->count());

        $inspect = \App\Support\Cooperative\CooperativeReconciliationViews::psnInspect($row);
        $this->assertContains('amount_not_divisible_by_unit_value', $inspect['psn_context']['failure_codes']);
        $this->assertContains('non_integral_legacy_total', $inspect['psn_context']['failure_codes']);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.reconciliation.resolvePsn', [
            'batch' => $batch,
            'row' => $row,
        ]), [
            'outcome' => CooperativeImportReviewOutcome::ConfirmedNoPsnParticipation->value,
        ]);

        $this->assertSame(0, CooperativeImportReviewItem::query()
            ->where('batch_id', $batch->id)
            ->where('import_row_id', $row->id)
            ->where('category', CooperativeImportReviewCategory::PsnUnconfirmed)
            ->where('status', CooperativeImportReviewStatus::Pending)
            ->count());
    }

    public function test_genuine_unrelated_warning_may_still_use_other_legacy(): void
    {
        $this->assertSame(
            CooperativeImportReviewCategory::OtherLegacy,
            ImportReviewSynchronizer::canonicalCategoryForWarning('malformed_date'),
        );
        $this->assertSame(
            CooperativeImportReviewCategory::PsnUnconfirmed,
            ImportReviewSynchronizer::canonicalCategoryForWarning('amount_not_divisible_by_unit_value'),
        );
        $this->assertSame(
            CooperativeImportReviewCategory::PsnUnconfirmed,
            ImportReviewSynchronizer::canonicalCategoryForWarning('non_integral_legacy_total'),
        );
    }

    public function test_sync_preserves_import_row_warnings_and_does_not_touch_canonical(): void
    {
        [$batch, , $row] = $this->committedBatchWithPsnReview();
        $errorsBefore = $row->validation_errors;
        $membersBefore = Member::query()->count();
        $fundsBefore = \App\Models\CooperativeFundEntry::query()->count();
        $psnBefore = ParticipationEntry::query()->count();

        app(ImportReviewSynchronizer::class)->sync($batch);
        app(ImportReviewSynchronizer::class)->sync($batch);

        $this->assertSame($errorsBefore, $row->fresh()->validation_errors);
        $this->assertSame($membersBefore, Member::query()->count());
        $this->assertSame($fundsBefore, \App\Models\CooperativeFundEntry::query()->count());
        $this->assertSame($psnBefore, ParticipationEntry::query()->count());
    }

    public function test_prune_removes_pending_misclassified_other_legacy_psn_duplicates(): void
    {
        [$batch, , $row] = $this->committedBatchWithPsnReview();

        $duplicate = CooperativeImportReviewItem::query()->create([
            'batch_id' => $batch->id,
            'import_row_id' => $row->id,
            'category' => CooperativeImportReviewCategory::OtherLegacy,
            'warning_code' => 'amount_not_divisible_by_unit_value',
            'status' => CooperativeImportReviewStatus::Pending,
        ]);

        $result = app(ImportReviewSynchronizer::class)->pruneMisclassifiedPsnOtherLegacy($batch);

        $this->assertSame(1, $result['removed']);
        $this->assertDatabaseMissing('cooperative_import_review_items', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('cooperative_import_review_items', [
            'batch_id' => $batch->id,
            'import_row_id' => $row->id,
            'category' => CooperativeImportReviewCategory::PsnUnconfirmed->value,
            'warning_code' => 'amount_not_divisible_by_unit_value',
        ]);
    }

    /**
     * @return array{0: CooperativeImportBatch, 1: User, 2: CooperativeImportReviewItem}
     */
    private function committedBatchWithProfileWarning(): array
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Profil Kosong',
            'Total' => '1.000.000',
            'No. Saham' => '33-001201',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('profile-review.csv', $csv),
        ]);

        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($batch, $staff);
        app(ImportReviewSynchronizer::class)->sync($batch->fresh());

        $item = CooperativeImportReviewItem::query()
            ->where('category', CooperativeImportReviewCategory::ProfileIncomplete)
            ->where('warning_code', 'missing_email')
            ->firstOrFail();

        return [$batch->fresh(), $staff, $item];
    }

    /**
     * @return array{0: CooperativeImportBatch, 1: User}
     */
    private function committedBatchWithSharedEmail(): array
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([
            [
                'Nama' => 'Email Satu',
                'Email' => 'shared@example.test',
                'Telepon' => '081200000401',
                'Total' => '1.000.000',
                'No. Saham' => '33-001301',
            ],
            [
                'Nama' => 'Email Dua',
                'Email' => 'shared@example.test',
                'Telepon' => '081200000402',
                'Total' => '1.000.000',
                'No. Saham' => '33-001302',
            ],
        ]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('shared-email.csv', $csv),
        ]);

        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($batch, $staff);
        app(ImportReviewSynchronizer::class)->sync($batch->fresh());

        return [$batch->fresh(), $staff];
    }

    /**
     * @return array{0: CooperativeImportBatch, 1: User}
     */
    private function committedBatchWithSharedPhone(): array
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([
            [
                'Nama' => 'Phone Satu',
                'Email' => 'phone1@example.test',
                'Telepon' => '081200000501',
                'Total' => '1.000.000',
                'No. Saham' => '33-001401',
            ],
            [
                'Nama' => 'Phone Dua',
                'Email' => 'phone2@example.test',
                'Telepon' => '081200000501',
                'Total' => '1.000.000',
                'No. Saham' => '33-001402',
            ],
        ]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('shared-phone.csv', $csv),
        ]);

        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($batch, $staff);
        app(ImportReviewSynchronizer::class)->sync($batch->fresh());

        return [$batch->fresh(), $staff];
    }

    /**
     * @return array{0: CooperativeImportBatch, 1: User, 2: \App\Models\CooperativeImportRow}
     */
    private function committedBatchWithPsnReview(): array
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'PSN Manual',
            'Email' => 'psn.manual@example.test',
            'Telepon' => '081200000601',
            'Alamat' => 'Jl. PSN',
            'Simpanan Pokok' => '1.000.000',
            'Simpanan Sukarela' => '500.000',
            'Total' => '1.500.000',
            'No. Saham' => '33-001501',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('psn-manual.csv', $csv),
        ]);

        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($batch, $staff);
        $row = $batch->rows()->firstOrFail();
        app(ImportReviewSynchronizer::class)->sync($batch->fresh());

        return [$batch->fresh(), $staff, $row->fresh()];
    }

    private function importStaff(): User
    {
        return $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
    }
}
