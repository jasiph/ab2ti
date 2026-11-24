<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeImportRowStatus;
use App\Enums\CooperativeMatchMethod;
use App\Enums\MemberSourceType;
use App\Models\CooperativeImportBatch;
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

class CooperativeLegacyMemberBootstrapTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_unmatched_valid_person_is_proposed_and_preview_does_not_create_member(): void
    {
        $staff = $this->importStaff();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv([CooperativeLegacyFixture::patternRows()[0]]),
        ]);

        $batch = CooperativeImportBatch::query()->firstOrFail();
        $row = $batch->rows()->firstOrFail();

        $this->assertSame(CooperativeImportRowStatus::Ready, $row->validation_status);
        $this->assertSame(CooperativeMatchMethod::ProposedLegacyCreate, $row->match_method);
        $this->assertTrue($row->normalized_data['propose_create_member']);
        $this->assertSame('proposed_legacy_create', $row->normalized_data['member_resolution']);
        $this->assertNull($row->matched_member_id);
        $this->assertSame(1, $batch->summary_json['proposed_legacy_members']);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('cooperative_memberships', 0);
        $this->assertDatabaseCount('membership_applications', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_commit_atomically_creates_member_membership_funds_and_psn(): void
    {
        $staff = $this->importStaff();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv([CooperativeLegacyFixture::patternRows()[0]]),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();
        $applicationsBefore = MembershipApplication::query()->count();
        $usersBefore = User::query()->count();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.commit', $batch), [
            'confirm' => '1',
        ]);

        $member = Member::query()->firstOrFail();
        $row = $batch->fresh()->rows()->firstOrFail();

        $this->assertSame('Sari Utami', $member->legal_name);
        $this->assertSame(MemberSourceType::CooperativeLegacyImport, $member->source_type);
        $this->assertNull($member->source_application_id);
        $this->assertNull($member->user_id);
        $this->assertSame($member->id, $row->created_member_id);
        $this->assertTrue($member->isActiveCooperativeMember());
        $this->assertDatabaseCount('cooperative_memberships', 1);
        $this->assertDatabaseCount('cooperative_fund_entries', 2);
        $this->assertDatabaseCount('participation_entries', 1);
        $this->assertSame($applicationsBefore, MembershipApplication::query()->count());
        $this->assertSame($usersBefore, User::query()->count());

        $opening = ParticipationEntry::query()->firstOrFail();
        $this->assertSame('cooperative_legacy_import', $opening->provenance['source_type']);
        $this->assertSame($batch->id, $opening->provenance['import_batch_id']);
        $this->assertSame($row->id, $opening->provenance['legacy_source_row_id']);
        $this->assertSame($staff->id, $opening->provenance['imported_by']);
        $this->assertSame('proposed_legacy_create', $opening->provenance['member_resolution']);
        $this->assertSame($member->id, $opening->provenance['created_member_id']);
    }

    public function test_failed_financial_write_rolls_back_new_member(): void
    {
        $staff = $this->importStaff();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv([CooperativeLegacyFixture::patternRows()[0]]),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();

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

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('cooperative_memberships', 1);
        $this->assertDatabaseCount('cooperative_fund_entries', 2);
        $this->assertDatabaseCount('participation_entries', 0);
        $this->assertDatabaseCount('membership_applications', 0);
        $row = $batch->fresh()->rows()->firstOrFail();
        $this->assertNotNull($row->member_committed_at);
        $this->assertNull($row->psn_committed_at);
        $this->assertNull($row->committed_at);
    }

    public function test_second_commit_and_reimport_do_not_duplicate_member(): void
    {
        $staff = $this->importStaff();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv([CooperativeLegacyFixture::patternRows()[0]], 'first.csv'),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();
        app(ImportCommitter::class)->commit($batch, $staff);

        $this->assertDatabaseCount('members', 1);

        try {
            app(ImportCommitter::class)->commit($batch->fresh(), $staff);
            $this->fail('Second commit should fail');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('members', 1);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv([CooperativeLegacyFixture::patternRows()[0]], 'second.csv'),
        ]);

        $this->assertDatabaseCount('members', 1);
        $this->assertSame(1, Member::query()->where('email', 'sari.utami@example.test')->count());
    }

    public function test_existing_matching_member_is_reused(): void
    {
        $staff = $this->importStaff();
        $existing = Member::factory()->create(['email' => 'sari.utami@example.test']);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv([CooperativeLegacyFixture::patternRows()[0]]),
        ]);
        $batch = CooperativeImportBatch::query()->firstOrFail();
        $row = $batch->rows()->firstOrFail();

        $this->assertSame($existing->id, $row->matched_member_id);
        $this->assertFalse($row->normalized_data['propose_create_member']);
        $this->assertSame('existing', $row->normalized_data['member_resolution']);

        app(ImportCommitter::class)->commit($batch, $staff);

        $this->assertDatabaseCount('members', 1);
        $this->assertNull($batch->fresh()->rows()->first()->created_member_id);
        $this->assertSame($existing->id, $existing->fresh()->cooperativeMembership->member_id);
    }

    public function test_ambiguous_and_name_only_matches_need_review(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sama@example.test', 'legal_name' => 'Alpha']);
        Member::factory()->create(['email' => 'sama@example.test', 'legal_name' => 'Beta']);
        Member::factory()->create(['legal_name' => 'Nama Sama Unik', 'email' => 'unik.existing@example.test']);

        $csv = CooperativeLegacyFixture::csv([
            [
                'Nama' => 'Alpha',
                'Email' => 'sama@example.test',
                'Telepon' => '081200000010',
                'Alamat' => 'Jl. Review 1',
                'Total' => '1.000.000',
                'No. Saham' => '33-000401',
            ],
            [
                'Nama' => 'Nama Sama Unik',
                'Email' => 'nama.baru@example.test',
                'Telepon' => '081200000011',
                'Alamat' => 'Jl. Review 2',
                'Total' => '1.000.000',
                'No. Saham' => '33-000402',
            ],
        ]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('review.csv', $csv),
        ]);

        $batch = CooperativeImportBatch::query()->firstOrFail();
        $this->assertTrue($batch->rows->contains(fn ($row) => in_array('ambiguous_member', $row->validation_errors ?? [], true)));
        $this->assertTrue($batch->rows->contains(fn ($row) => in_array('name_collision', $row->validation_errors ?? [], true)));
        $this->assertFalse($batch->rows->contains(fn ($row) => ($row->normalized_data['propose_create_member'] ?? false) === true));
        $this->assertDatabaseCount('members', 3);
    }

    public function test_identity_ready_share_review_does_not_auto_commit(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Fraction Legacy',
            'Email' => 'fraction.legacy@example.test',
            'Telepon' => '081200000020',
            'Alamat' => 'Jl. Fraction 1',
            'Total' => '1.500.000',
            'No. Saham' => '33-000299',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('share.csv', $csv),
        ]);

        $row = CooperativeImportBatch::query()->firstOrFail()->rows()->firstOrFail();
        $this->assertTrue($row->normalized_data['propose_create_member']);
        $this->assertSame('ready', $row->normalized_data['components']['member']['status']);
        $this->assertSame('ready', $row->normalized_data['components']['cooperative_membership']['status']);
        $this->assertSame('needs_review', $row->normalized_data['components']['psn']['status']);
        $this->assertSame(CooperativeImportRowStatus::NeedsReview, $row->validation_status);
        $this->assertContains('non_integral_legacy_total', $row->validation_errors);
        $this->assertNotEmpty($row->normalized_data['review_categories']['share']);
        $this->assertEmpty($row->normalized_data['review_categories']['identity']);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_historical_ab2ti_identifier_can_become_membership_number(): void
    {
        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([[
            'Nama' => 'Identifier Fixture',
            'NIK' => 'AB2TI/08/I/0005',
            'Email' => 'identifier.fixture@example.test',
            'Telepon' => '081200000030',
            'Alamat' => 'Jl. Identifier 1',
            'Total' => '1.000.000',
            'No. Saham' => '33-000410',
        ]]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('id.csv', $csv),
        ]);
        app(ImportCommitter::class)->commit(CooperativeImportBatch::query()->firstOrFail(), $staff);

        $member = Member::query()->firstOrFail();
        $this->assertSame('AB2TI/08/I/0005', $member->membership_number);
        $this->assertSame(MemberSourceType::CooperativeLegacyImport, $member->source_type);
    }

    private function importStaff()
    {
        return $this->userWithPermissions(['cooperative.view', 'cooperative.manage']);
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function uploadedCsv(array $rows, string $name = 'legacy.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, CooperativeLegacyFixture::csv($rows));
    }
}
