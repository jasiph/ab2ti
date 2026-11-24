<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeImportRowStatus;
use App\Enums\MemberSourceType;
use App\Models\CooperativeFundEntry;
use App\Models\CooperativeImportBatch;
use App\Models\Member;
use App\Models\User;
use App\Support\Cooperative\Import\ImportCommitter;
use App\Support\Cooperative\Import\ImportReverser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithAcl;
use Tests\Support\CooperativeLegacyFixture;
use Tests\TestCase;

class CooperativeTrustedMembershipClassificationTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_name_only_trusted_person_is_proposed_with_profile_warnings(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Hanya Nama',
            'Total' => '1.000.000',
            'No. Saham' => '33-000501',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('name-only.csv', $csv),
        ]);

        $row = CooperativeImportBatch::query()->firstOrFail()->rows()->firstOrFail();
        $this->assertTrue($row->normalized_data['propose_create_member']);
        $this->assertSame('proposed_legacy_create', $row->normalized_data['member_resolution']);
        $this->assertSame('ready', $row->normalized_data['components']['member']['status']);
        $this->assertContains('missing_email', $row->normalized_data['profile_warnings']);
        $this->assertContains('missing_phone', $row->normalized_data['profile_warnings']);
        $this->assertContains('missing_address', $row->normalized_data['profile_warnings']);
        $this->assertSame(CooperativeImportRowStatus::Ready, $row->validation_status);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_missing_contact_does_not_invalidate_membership_and_no_placeholders_are_created(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Profil Kosong',
            'Total' => '1.000.000',
            'No. Saham' => '33-000502',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('profile.csv', $csv),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($batch, $staff);

        $member = Member::query()->firstOrFail();
        $this->assertSame(MemberSourceType::CooperativeLegacyImport, $member->source_type);
        $this->assertNull($member->email);
        $this->assertNull($member->phone);
        $this->assertNull($member->address);
        $this->assertTrue($member->isActiveCooperativeMember());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_source_duplicate_nik_and_identifier_go_to_review_without_writing_nik(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([
            [
                'Nama' => 'Duplikat Satu',
                'NIK' => '3174010101900001',
                'Email' => 'dup.satu@example.test',
                'Telepon' => '081200000101',
                'Alamat' => 'Jl. Satu',
                'Total' => '1.000.000',
                'No. Saham' => '33-000601',
            ],
            [
                'Nama' => 'Duplikat Dua',
                'NIK' => '3174010101900001',
                'Email' => 'dup.dua@example.test',
                'Telepon' => '081200000102',
                'Alamat' => 'Jl. Dua',
                'Total' => '1.000.000',
                'No. Saham' => '33-000602',
            ],
            [
                'Nama' => 'Id Satu',
                'NIK' => 'AB2TI/08/I/0005',
                'Email' => 'id.satu@example.test',
                'Telepon' => '081200000103',
                'Alamat' => 'Jl. Id 1',
                'Total' => '1.000.000',
                'No. Saham' => '33-000603',
            ],
            [
                'Nama' => 'Id Dua',
                'NIK' => 'AB2TI/08/I/0005',
                'Email' => 'id.dua@example.test',
                'Telepon' => '081200000104',
                'Alamat' => 'Jl. Id 2',
                'Total' => '1.000.000',
                'No. Saham' => '33-000604',
            ],
        ]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('dup.csv', $csv),
        ]);

        $batch = CooperativeImportBatch::query()->firstOrFail();
        $this->assertGreaterThanOrEqual(4, $batch->summary_json['source_duplicate_conflict_rows']);
        $this->assertTrue($batch->rows->every(fn ($row) => in_array('source_duplicate_nik', $row->validation_errors ?? [], true)
            || in_array('source_duplicate_identifier', $row->validation_errors ?? [], true)));
        $this->assertFalse($batch->rows->contains(fn ($row) => ($row->normalized_data['propose_create_member'] ?? false) === true));
        $this->assertDatabaseCount('members', 0);
        $this->assertFalse(Schema::hasColumn('members', 'nik'));
    }

    public function test_component_commit_creates_member_membership_funds_without_guessed_psn(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Saham Ambigu',
            'Email' => 'saham.ambigu@example.test',
            'Telepon' => '081200000201',
            'Alamat' => 'Jl. Ambigu',
            'Simpanan Pokok' => '1.000.000',
            'Simpanan Sukarela' => '500.000',
            'Total' => '1.500.000',
            'No. Saham' => '33-000701',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('psn-review.csv', $csv),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();
        $row = $batch->rows()->firstOrFail();
        $this->assertSame('ready', $row->normalized_data['components']['member']['status']);
        $this->assertSame('ready', $row->normalized_data['components']['cooperative_membership']['status']);
        $this->assertSame('ready', $row->normalized_data['components']['funds']['status']);
        $this->assertSame('needs_review', $row->normalized_data['components']['psn']['status']);

        app(ImportCommitter::class)->commit($batch, $staff);

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('cooperative_memberships', 1);
        $this->assertDatabaseCount('cooperative_fund_entries', 2);
        $this->assertDatabaseCount('participation_entries', 0);

        $row = $batch->fresh()->rows()->firstOrFail();
        $this->assertNotNull($row->member_committed_at);
        $this->assertNotNull($row->membership_committed_at);
        $this->assertNotNull($row->funds_committed_at);
        $this->assertNull($row->psn_committed_at);
        $this->assertNull($row->committed_at);
        $this->assertSame(CooperativeImportRowStatus::NeedsReview, $row->validation_status);
    }

    public function test_later_psn_resolution_does_not_duplicate_earlier_components(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Saham Ambigu',
            'Email' => 'saham.later@example.test',
            'Telepon' => '081200000202',
            'Alamat' => 'Jl. Later',
            'Simpanan Pokok' => '1.000.000',
            'Simpanan Sukarela' => '500.000',
            'Total' => '1.500.000',
            'No. Saham' => '33-000702',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('later.csv', $csv),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($batch, $staff);

        $row = $batch->fresh()->rows()->firstOrFail();
        $data = $row->normalized_data;
        $data['units'] = 2;
        $data['units_origin'] = 'explicit';
        $data['explicit_units'] = 2;
        $data['share_reconciled'] = true;
        $data['components']['psn']['status'] = 'ready';
        $data['review_categories']['share'] = [];
        $row->forceFill([
            'normalized_data' => $data,
            'validation_status' => CooperativeImportRowStatus::Ready,
            'validation_errors' => $data['profile_warnings'] ?? [],
        ])->save();

        app(ImportCommitter::class)->commit($batch->fresh(), $staff);

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('cooperative_memberships', 1);
        $this->assertDatabaseCount('cooperative_fund_entries', 2);
        $this->assertDatabaseCount('participation_entries', 1);
        $this->assertNotNull($batch->fresh()->rows()->first()->psn_committed_at);
        $this->assertNotNull($batch->fresh()->rows()->first()->committed_at);
    }

    public function test_reversal_respects_partial_component_commit_and_blocks_linked_members(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Reverse Partial',
            'Email' => 'reverse.partial@example.test',
            'Telepon' => '081200000203',
            'Alamat' => 'Jl. Reverse',
            'Simpanan Pokok' => '1.000.000',
            'Simpanan Sukarela' => '500.000',
            'Total' => '1.500.000',
            'No. Saham' => '33-000703',
        ]]);
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('reverse.csv', $csv),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($batch, $staff);

        $this->assertTrue($batch->fresh()->canReverse());
        app(ImportReverser::class)->reverse($batch->fresh(), $staff);
        $this->assertNotNull(CooperativeFundEntry::query()->first()->reversed_at);
        $this->assertDatabaseCount('participation_entries', 0);

        $csv2 = CooperativeLegacyFixture::csv([[
            'Nama' => 'Reverse Linked',
            'Email' => 'reverse.linked@example.test',
            'Telepon' => '081200000204',
            'Alamat' => 'Jl. Linked',
            'Total' => '1.000.000',
            'No. Saham' => '33-000704',
        ]]);
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('linked.csv', $csv2),
        ]);
        $batch2 = CooperativeImportBatch::query()->orderByDesc('id')->firstOrFail();
        app(ImportCommitter::class)->commit($batch2, $staff);
        $member = Member::query()->where('email', 'reverse.linked@example.test')->firstOrFail();
        $member->forceFill(['user_id' => User::factory()->create()->id, 'linked_at' => now()])->save();

        $this->expectException(\RuntimeException::class);
        app(ImportReverser::class)->reverse($batch2->fresh(), $staff);
    }

    private function importStaff()
    {
        return $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
    }
}
