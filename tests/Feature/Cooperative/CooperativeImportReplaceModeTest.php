<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportMode;
use App\Enums\MemberSourceType;
use App\Models\CooperativeFundEntry;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\ParticipationEntry;
use App\Models\User;
use App\Support\Cooperative\Import\ImportCommitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithAcl;
use Tests\Support\CooperativeLegacyFixture;
use Tests\TestCase;

class CooperativeImportReplaceModeTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_default_mode_is_append_and_checkbox_is_not_preselected(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();

        $this->assertSame(CooperativeImportMode::Append, $batch->import_mode);
        $this->actingAs($staff)
            ->get(route('admin.cooperative.imports.show', $batch))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('batch.default_import_mode', 'append')
                ->where('batch.import_mode', 'append')
                ->has('batch.replacement_impact'));
    }

    public function test_append_preserves_previous_import_and_blocks_identical_commit(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv('first.csv'),
        ]);
        $first = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($first, $staff);

        $membersAfterFirst = Member::query()->count();
        $fundsAfterFirst = CooperativeFundEntry::query()->count();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv('second.csv'),
        ]);
        $second = CooperativeImportBatch::query()->orderByDesc('id')->firstOrFail();

        $this->expectException(\RuntimeException::class);
        try {
            app(ImportCommitter::class)->commit($second, $staff, CooperativeImportMode::Append);
        } finally {
            $this->assertSame($membersAfterFirst, Member::query()->count());
            $this->assertSame($fundsAfterFirst, CooperativeFundEntry::query()->count());
        }
    }

    public function test_replace_mode_requires_acknowledgement(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.commit', $batch), [
                'confirm' => '1',
                'replace_legacy' => '1',
                'replace_acknowledged' => '0',
            ])
            ->assertRedirect(route('admin.cooperative.imports.show', $batch))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cooperative_memberships', 0);
    }

    public function test_replace_mode_replaces_eligible_legacy_data_and_keeps_audit(): void
    {
        $staff = $this->importStaff();
        $rows = [[
            'Nama' => 'Replace Audit',
            'Email' => 'replace.audit@example.test',
            'Telepon' => '081299990010',
            'Alamat' => 'Jl. Audit',
            'Simpanan Pokok' => '1.000.000',
            'Total' => '1.000.000',
            'No. Saham' => '33-000910',
        ]];
        $csv = CooperativeLegacyFixture::csv($rows);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('legacy-a.csv', $csv),
        ]);
        $first = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($first, $staff);
        $this->assertSame(1, Member::query()->where('source_type', MemberSourceType::CooperativeLegacyImport)->count());

        $approved = Member::factory()->create([
            'email' => 'approved.keep@example.test',
        ]);
        $applicationsBefore = MembershipApplication::query()->count();
        $usersBefore = User::query()->count();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('legacy-b.csv', $csv),
        ]);
        $second = CooperativeImportBatch::query()->orderByDesc('id')->firstOrFail();

        app(ImportCommitter::class)->commit(
            $second,
            $staff,
            CooperativeImportMode::ReplaceLegacy,
            true,
        );

        $second = $second->fresh();
        $first = $first->fresh();

        $this->assertSame(CooperativeImportMode::ReplaceLegacy, $second->import_mode);
        $this->assertSame(CooperativeImportBatchStatus::Replaced, $first->status);
        $this->assertSame($second->id, $first->replaced_by_batch_id);
        $this->assertSame($first->id, $second->replaces_batch_id);
        $this->assertTrue($second->status->isTerminalSuccess());
        $this->assertNotNull($second->summary_json['replacement_report'] ?? null);

        $this->assertDatabaseHas('members', ['id' => $approved->id, 'email' => 'approved.keep@example.test']);
        $this->assertSame($applicationsBefore, MembershipApplication::query()->count());
        $this->assertSame($usersBefore, User::query()->count());

        $this->assertSame(1, Member::query()->where('source_type', MemberSourceType::CooperativeLegacyImport)->count());
    }

    public function test_replace_does_not_delete_user_linked_legacy_member(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Linked Legacy',
            'Email' => 'linked.legacy@example.test',
            'Telepon' => '081211110001',
            'Alamat' => 'Jl. Linked',
            'Total' => '1.000.000',
            'No. Saham' => '33-000801',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('linked.csv', $csv),
        ]);
        $first = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($first, $staff);

        $member = Member::query()->where('email', 'linked.legacy@example.test')->firstOrFail();
        $member->forceFill([
            'user_id' => User::factory()->create()->id,
            'linked_at' => now(),
        ])->save();

        $csv2 = CooperativeLegacyFixture::csv([[
            'Nama' => 'Replacement Person',
            'Email' => 'replacement.person@example.test',
            'Telepon' => '081211110002',
            'Alamat' => 'Jl. New',
            'Total' => '1.000.000',
            'No. Saham' => '33-000802',
        ]]);
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('replace.csv', $csv2),
        ]);
        $second = CooperativeImportBatch::query()->orderByDesc('id')->firstOrFail();
        app(ImportCommitter::class)->commit($second, $staff, CooperativeImportMode::ReplaceLegacy, true);

        $this->assertDatabaseHas('members', ['id' => $member->id, 'email' => 'linked.legacy@example.test']);
        $report = $second->fresh()->summary_json['replacement_report'];
        $this->assertSame(1, $report['old_legacy_members_preserved']);
        $this->assertContains($member->id, $report['preserved_member_ids']);
    }

    public function test_replace_preserves_source_duplicates_as_separate_members_with_warnings(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([
            [
                'Nama' => 'Duplikat A',
                'NIK' => '3174010101900099',
                'Email' => 'dup.shared@example.test',
                'Telepon' => '081299990001',
                'Alamat' => 'Jl. A',
                'Total' => '1.000.000',
                'No. Saham' => '33-000901',
            ],
            [
                'Nama' => 'Duplikat B',
                'NIK' => '3174010101900099',
                'Email' => 'dup.shared@example.test',
                'Telepon' => '081299990001',
                'Alamat' => 'Jl. B',
                'Total' => '1.000.000',
                'No. Saham' => '33-000902',
            ],
        ]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('dups.csv', $csv),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();
        $this->assertSame(2, $batch->summary_json['source_duplicate_conflict_rows']);

        app(ImportCommitter::class)->commit($batch, $staff, CooperativeImportMode::ReplaceLegacy, true);

        $this->assertDatabaseCount('members', 2);
        $this->assertDatabaseCount('cooperative_memberships', 2);
        $rows = $batch->fresh()->rows;
        $this->assertTrue($rows->every(fn ($row) => in_array('possible_duplicate_nik', $row->validation_errors ?? [], true)
            || in_array('shared_email', $row->validation_errors ?? [], true)));
    }

    public function test_replace_does_not_fabricate_psn_units(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Saham Ambigu Replace',
            'Email' => 'saham.replace@example.test',
            'Telepon' => '081288880001',
            'Alamat' => 'Jl. Ambigu',
            'Simpanan Pokok' => '1.000.000',
            'Simpanan Sukarela' => '500.000',
            'Total' => '1.500.000',
            'No. Saham' => '33-000903',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('psn.csv', $csv),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($batch, $staff, CooperativeImportMode::ReplaceLegacy, true);

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('cooperative_memberships', 1);
        $this->assertDatabaseCount('cooperative_fund_entries', 2);
        $this->assertDatabaseCount('participation_entries', 0);
        $this->assertNull($batch->fresh()->rows()->first()->psn_committed_at);
    }

    public function test_repeat_replace_does_not_grow_canonical_counts(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Stable Person',
            'Email' => 'stable.person@example.test',
            'Telepon' => '081277770001',
            'Alamat' => 'Jl. Stable',
            'Total' => '1.000.000',
            'No. Saham' => '33-000904',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('r1.csv', $csv),
        ]);
        $first = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($first, $staff, CooperativeImportMode::ReplaceLegacy, true);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('r2.csv', $csv),
        ]);
        $second = CooperativeImportBatch::query()->orderByDesc('id')->firstOrFail();
        app(ImportCommitter::class)->commit($second, $staff, CooperativeImportMode::ReplaceLegacy, true);

        $this->assertSame(1, Member::query()->count());
        $this->assertSame(1, CooperativeMembership::query()->count());
        $this->assertSame(1, ParticipationEntry::query()->where('entry_type', 'opening_balance')->count());
        $this->assertSame(CooperativeImportBatchStatus::Replaced, $first->fresh()->status);
    }

    public function test_preview_includes_replacement_impact_counts(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv('impact-a.csv'),
        ]);
        app(ImportCommitter::class)->commit(CooperativeImportBatch::query()->firstOrFail(), $staff);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv('impact-b.csv'),
        ]);
        $batch = CooperativeImportBatch::query()->orderByDesc('id')->firstOrFail();

        $this->actingAs($staff)
            ->get(route('admin.cooperative.imports.show', $batch))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('batch.replacement_impact.import_batches_affected', 1)
                ->where('batch.replacement_impact.historical_fund_entries', fn ($v) => $v > 0));
    }

    private function importStaff()
    {
        return $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
    }

    private function seedReadyMembers(): void
    {
        Member::factory()->create(['email' => 'sari.utami@example.test']);
        Member::factory()->create(['email' => 'anton.setiawan@example.test']);
        Member::factory()->create(['email' => 'darto.prakoso@example.test']);
        Member::factory()->create(['email' => 'dukut.raharjo@example.test']);
    }

    private function uploadedCsv(string $name = 'legacy.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            CooperativeLegacyFixture::csv(CooperativeLegacyFixture::patternRows()),
        );
    }
}
