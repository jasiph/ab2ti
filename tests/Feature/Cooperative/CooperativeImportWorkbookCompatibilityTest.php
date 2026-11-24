<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeImportBatchStatus;
use App\Models\CooperativeImportBatch;
use App\Models\Member;
use App\Support\Cooperative\Import\ImportCommitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAcl;
use Tests\Support\CooperativeLegacyWorkbookFixture;
use Tests\TestCase;

class CooperativeImportWorkbookCompatibilityTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_multi_sheet_workbook_requires_explicit_worksheet_selection(): void
    {
        $staff = $this->importStaff();

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.store'), [
                'file' => CooperativeLegacyWorkbookFixture::uploadedFile(),
            ])
            ->assertRedirect();

        $batch = CooperativeImportBatch::query()->firstOrFail();
        $sheets = collect($batch->worksheets_json);

        $this->assertSame(CooperativeImportBatchStatus::Uploaded, $batch->status);
        $this->assertTrue($batch->needsSheetSelection());
        $this->assertNull($batch->worksheet_name);
        $this->assertSame(0, $batch->rows()->count());
        $this->assertCount(6, $sheets);
        $this->assertTrue($sheets->contains(fn (array $sheet) => $sheet['name'] === 'A.Anggota OK' && $sheet['candidate'] === true && $sheet['visible'] === true && $sheet['header_row'] === 4));
        $this->assertTrue($sheets->contains(fn (array $sheet) => $sheet['name'] === 'List Anggota' && $sheet['visible'] === false));
        $this->assertTrue($sheets->contains(fn (array $sheet) => $sheet['name'] === 'Kwitansi' && $sheet['visible'] === false));
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('cooperative_memberships', 0);
    }

    public function test_selected_anggota_sheet_is_parsed_alone_with_header_and_footers(): void
    {
        $staff = $this->importStaff();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => CooperativeLegacyWorkbookFixture::uploadedFile('select.xlsx'),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.selectWorksheet', $batch), [
                'worksheet_name' => 'A.Anggota OK',
            ])
            ->assertRedirect();

        $batch = $batch->fresh(['rows']);
        $this->assertSame('A.Anggota OK', $batch->worksheet_name);
        $this->assertSame(4, $batch->header_row_number);
        $this->assertSame(4, $batch->rows()->count());
        $this->assertGreaterThan(0, $batch->summary_json['ignored_footer_or_summary_rows']);
        $this->assertFalse($batch->rows->contains(fn ($row) => ($row->normalized_data['name'] ?? null) === 'Laki-laki'));
        $this->assertFalse($batch->rows->contains(fn ($row) => ($row->normalized_data['name'] ?? null) === 'Total'));
        $this->assertGreaterThan(0, $batch->summary_json['proposed_legacy_members']);
        $this->assertGreaterThan(0, $batch->summary_json['share_review_rows']);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('participation_entries', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_hidden_and_other_sheets_do_not_invalidate_selected_sheet(): void
    {
        $staff = $this->importStaff();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => CooperativeLegacyWorkbookFixture::uploadedFile('other.xlsx'),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.selectWorksheet', $batch), [
            'worksheet_name' => 'A.Anggota OK',
        ]);

        $this->assertNotSame(CooperativeImportBatchStatus::Failed, $batch->fresh()->status);
        $this->assertSame(4, $batch->fresh()->rows()->count());
    }

    public function test_worksheet_aware_duplicate_protection_allows_another_sheet(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'investasi.fixture@example.test']);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => CooperativeLegacyWorkbookFixture::uploadedFile('dup-a.xlsx'),
        ]);
        $first = CooperativeImportBatch::query()->firstOrFail();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.selectWorksheet', $first), [
            'worksheet_name' => 'A.Anggota OK',
        ]);
        $ready = $first->fresh()->rows()->where('validation_status', 'ready')->get();
        $this->assertNotEmpty($ready);
        app(ImportCommitter::class)->commit($first->fresh(), $staff);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => CooperativeLegacyWorkbookFixture::uploadedFile('dup-b.xlsx'),
        ]);
        $second = CooperativeImportBatch::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.selectWorksheet', $second), [
            'worksheet_name' => 'B.Investasi OK',
        ]);

        $this->assertNull($second->fresh()->duplicate_of_batch_id);
        $this->assertSame('B.Investasi OK', $second->fresh()->worksheet_name);
        $this->assertSame(1, $second->fresh()->rows()->count());
        $this->assertSame('A.Anggota OK', $first->fresh()->worksheet_name);
    }

    private function importStaff()
    {
        return $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
    }
}
