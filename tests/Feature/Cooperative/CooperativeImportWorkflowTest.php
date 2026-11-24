<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeFundType;
use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportRowStatus;
use App\Enums\CooperativeSourceType;
use App\Models\CooperativeFundEntry;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeMembership;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Support\Cooperative\Import\ImportCommitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithAcl;
use Tests\Support\CooperativeLegacyFixture;
use Tests\TestCase;

class CooperativeImportWorkflowTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_upload_creates_staging_without_canonical_records(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.store'), [
                'file' => $this->uploadedCsv(),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('cooperative_import_batches', 1);
        $this->assertDatabaseCount('cooperative_import_rows', 4);
        $this->assertDatabaseCount('cooperative_memberships', 0);
        $this->assertDatabaseCount('participation_entries', 0);
        $this->assertDatabaseCount('cooperative_fund_entries', 0);

        $batch = CooperativeImportBatch::query()->first();
        $this->assertNotSame(CooperativeImportBatchStatus::Committed, $batch->status);
        $this->assertGreaterThan(0, $batch->summary_json['ready']);
    }

    public function test_preview_identifies_ready_and_review_rows(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $csv = CooperativeLegacyFixture::csv([
            CooperativeLegacyFixture::patternRows()[0],
            [
                'Nama' => 'Tanpa Cocok',
                'Email' => 'tanpa.cocok@example.test',
                'Total' => '1.000.000',
                'No. Saham' => '33-000299',
            ],
            [
                'Nama' => 'Total Salah',
                'Email' => 'total.salah@example.test',
                'Simpanan Pokok' => '1.000.000',
                'Simpanan Sukarela' => '1.000.000',
                'Total' => '9.000.000',
                'No. Saham' => '33-000298 (2)',
            ],
        ]);

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent('preview.csv', $csv),
            ]);

        $batch = CooperativeImportBatch::query()->first();

        $this->assertSame(2, $batch->rows()->where('validation_status', CooperativeImportRowStatus::Ready)->count());
        $this->assertGreaterThanOrEqual(1, $batch->rows()->where('validation_status', CooperativeImportRowStatus::NeedsReview)->count());
        $this->assertTrue(
            $batch->rows->contains(fn ($row) => in_array('missing_phone', $row->normalized_data['profile_warnings'] ?? [], true)
                || in_array('missing_address', $row->normalized_data['profile_warnings'] ?? [], true))
        );
        $this->assertTrue(
            $batch->rows->contains(fn ($row) => in_array('total_mismatch', $row->validation_errors ?? [], true))
        );
    }

    public function test_duplicate_file_warns_and_does_not_duplicate_canonical_records(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv('first.csv'),
        ]);
        $first = CooperativeImportBatch::query()->first();
        app(ImportCommitter::class)->commit($first, $staff);

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.store'), [
                'file' => $this->uploadedCsv('second.csv'),
            ])
            ->assertRedirect();

        $second = CooperativeImportBatch::query()->orderByDesc('id')->first();
        $this->assertSame($first->id, $second->duplicate_of_batch_id);

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.commit', $second), [
                'confirm' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('participation_entries', 4);
        $this->assertDatabaseCount('cooperative_memberships', 4);
    }

    public function test_commit_creates_membership_funds_participation_and_provenance(): void
    {
        $staff = $this->importStaff();
        $existing = Member::factory()->create(['email' => 'sari.utami@example.test']);
        CooperativeMembership::factory()->create(['member_id' => $existing->id]);
        Member::factory()->create(['email' => 'anton.setiawan@example.test']);
        Member::factory()->create(['email' => 'darto.prakoso@example.test']);
        Member::factory()->create(['email' => 'dukut.raharjo@example.test']);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);

        $batch = CooperativeImportBatch::query()->first();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.commit', $batch), [
            'confirm' => '1',
        ]);

        $this->assertDatabaseCount('cooperative_memberships', 4);
        $this->assertTrue($existing->fresh()->isActiveCooperativeMember());

        $sariMembership = $existing->fresh()->cooperativeMembership;
        $this->assertSame(1_000_000, (int) CooperativeFundEntry::query()
            ->where('cooperative_membership_id', $sariMembership->id)
            ->where('fund_type', CooperativeFundType::PrincipalSaving)
            ->value('amount'));
        $this->assertSame(49_000_000, (int) CooperativeFundEntry::query()
            ->where('cooperative_membership_id', $sariMembership->id)
            ->where('fund_type', CooperativeFundType::VoluntarySaving)
            ->value('amount'));

        $opening = ParticipationEntry::query()
            ->where('legacy_source_row_id', $batch->rows()->orderBy('source_row_number')->first()->id)
            ->first();

        $this->assertNotNull($opening);
        $this->assertSame(50, (int) $opening->units);
        $this->assertSame('32-1-000181', $opening->share_reference);
        $this->assertSame(CooperativeSourceType::LegacyImport, $opening->source_type);
        $this->assertSame($batch->id, $opening->import_batch_id);
        $this->assertSame($staff->id, $opening->provenance['imported_by']);
        $this->assertSame('explicit', $opening->units_origin->value);
    }

    public function test_committed_row_cannot_be_committed_twice(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);
        $batch = CooperativeImportBatch::query()->first();
        app(ImportCommitter::class)->commit($batch, $staff);

        $this->expectException(\RuntimeException::class);
        app(ImportCommitter::class)->commit($batch->fresh(), $staff);
    }

    public function test_row_failure_does_not_partially_commit(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);
        $batch = CooperativeImportBatch::query()->first();

        ParticipationEntry::creating(function (): void {
            throw new \RuntimeException('forced-failure');
        });

        try {
            app(ImportCommitter::class)->commit($batch, $staff);
        } finally {
            ParticipationEntry::getEventDispatcher()->forget(
                'eloquent.creating: '.ParticipationEntry::class
            );
        }

        $this->assertDatabaseCount('participation_entries', 0);
        $this->assertTrue(
            $batch->rows()->whereNotNull('committed_at')->doesntExist()
        );
        $this->assertTrue(
            $batch->fresh()->rows->contains(fn ($row) => $row->member_committed_at !== null)
        );
    }

    public function test_xlsx_upload_is_supported(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $path = storage_path('app/private/cooperative-imports-test.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new \OpenSpout\Writer\XLSX\Writer;
        $writer->openToFile($path);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Nama', 'Email', 'Total', 'No. Saham',
        ]));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'Sari Utami', 'sari.utami@example.test', '1.000.000', '33-000231',
        ]));
        $writer->close();

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.store'), [
                'file' => new UploadedFile($path, 'legacy.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('cooperative_import_rows', 1);
        $this->assertDatabaseCount('participation_entries', 0);
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
