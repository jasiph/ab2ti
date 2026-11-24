<?php

namespace Tests\Feature\Cooperative;

use App\Enums\CooperativeImportBatchStatus;
use App\Enums\CooperativeImportRowStatus;
use App\Enums\ParticipationEntryType;
use App\Models\CooperativeBusiness;
use App\Models\CooperativeImportBatch;
use App\Models\CooperativeImportRow;
use App\Models\Member;
use App\Models\ParticipationEntry;
use App\Support\Cooperative\CooperativePublicStats;
use App\Support\Cooperative\Import\ImportCommitter;
use App\Support\Cooperative\Import\ImportReverser;
use App\Support\Cooperative\Import\MemberMatcher;
use App\Support\Cooperative\Import\RowNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAcl;
use Tests\Support\CooperativeLegacyFixture;
use Tests\TestCase;

class CooperativeImportHardeningTest extends TestCase
{
    use InteractsWithAcl;
    use RefreshDatabase;

    public function test_reconciliation_matrix_ready_review_and_inference_rules(): void
    {
        $sari = Member::factory()->create(['email' => 'sari.matrix@example.test']);
        $dukut = Member::factory()->create(['email' => 'dukut.matrix@example.test']);
        $anton = Member::factory()->create(['email' => 'anton.matrix@example.test']);
        $mismatch = Member::factory()->create(['email' => 'mismatch.matrix@example.test']);
        $fraction = Member::factory()->create(['email' => 'fraction.matrix@example.test']);
        $shareMismatch = Member::factory()->create(['email' => 'share.matrix@example.test']);
        $blankFunds = Member::factory()->create(['email' => 'blank.matrix@example.test']);
        $business = CooperativeBusiness::psn();
        $normalizer = new RowNormalizer;

        $a = $normalizer->normalize([
            'name' => 'Sari',
            'email' => $sari->email,
            'principal' => '1.000.000',
            'voluntary' => '49.000.000',
            'total' => '50.000.000',
            'share' => '32-1-000181 (50)',
        ], $business);
        $this->assertSame(CooperativeImportRowStatus::Ready, $a['status']);
        $this->assertSame(50, $a['normalized']['units']);
        $this->assertSame('explicit', $a['normalized']['units_origin']);

        $b = $normalizer->normalize([
            'name' => 'Dukut',
            'email' => $dukut->email,
            'principal' => '1.000.000',
            'voluntary' => '1.000.000',
            'total' => '2.000.000',
            'share' => '33-000233 (2)',
        ], $business);
        $this->assertSame(CooperativeImportRowStatus::Ready, $b['status']);
        $this->assertSame(2, $b['normalized']['units']);

        $c = $normalizer->normalize([
            'name' => 'Anton',
            'email' => $anton->email,
            'principal' => '1.000.000',
            'voluntary' => '',
            'total' => '1.000.000',
            'share' => '33-000231',
        ], $business);
        $this->assertSame(CooperativeImportRowStatus::Ready, $c['status']);
        $this->assertSame(1, $c['normalized']['units']);
        $this->assertSame('inferred', $c['normalized']['units_origin']);

        $d = $normalizer->normalize([
            'name' => 'Mismatch',
            'email' => $mismatch->email,
            'principal' => '1.000.000',
            'voluntary' => '2.000.000',
            'total' => '4.000.000',
            'share' => '33-000240 (4)',
        ], $business);
        $this->assertSame(CooperativeImportRowStatus::NeedsReview, $d['status']);
        $this->assertContains('total_mismatch', $d['errors']);

        $e = $normalizer->normalize([
            'name' => 'Fraction',
            'email' => $fraction->email,
            'principal' => '',
            'voluntary' => '',
            'total' => '2.500.000',
            'share' => '33-000241',
        ], $business);
        $this->assertSame(CooperativeImportRowStatus::NeedsReview, $e['status']);
        $this->assertContains('amount_not_divisible_by_unit_value', $e['errors']);
        $this->assertContains('non_integral_legacy_total', $e['errors']);
        $this->assertNull($e['normalized']['units']);

        $f = $normalizer->normalize([
            'name' => 'Share mismatch',
            'email' => $shareMismatch->email,
            'principal' => '',
            'voluntary' => '',
            'total' => '50.000.000',
            'share' => '32-1-000199 (49)',
        ], $business);
        $this->assertSame(CooperativeImportRowStatus::NeedsReview, $f['status']);
        $this->assertContains('share_unit_mismatch', $f['errors']);

        $g = $normalizer->normalize([
            'name' => 'Blank funds',
            'email' => $blankFunds->email,
            'principal' => '',
            'voluntary' => '',
            'total' => '1.000.000',
            'share' => '33-000242',
        ], $business);
        $this->assertSame(CooperativeImportRowStatus::Ready, $g['status']);
        $this->assertTrue($g['normalized']['principal_blank']);
        $this->assertTrue($g['normalized']['voluntary_blank']);
        $this->assertSame(0, $g['normalized']['principal_amount']);
        $this->assertSame(1, $g['normalized']['units']);
        $this->assertSame('inferred', $g['normalized']['units_origin']);
    }

    public function test_preview_totals_use_ready_rows_only(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $csv = CooperativeLegacyFixture::csv([
            CooperativeLegacyFixture::patternRows()[0],
            [
                'Nama' => 'Total Salah',
                'Email' => 'total.salah.preview@example.test',
                'Simpanan Pokok' => '1.000.000',
                'Simpanan Sukarela' => '1.000.000',
                'Total' => '9.000.000',
                'No. Saham' => '33-000298 (9)',
            ],
        ]);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('preview.csv', $csv),
        ]);

        $batch = CooperativeImportBatch::query()->first();
        $this->assertSame(1, $batch->summary_json['ready']);
        $this->assertSame(50, $batch->summary_json['proposed_total_psn_units']);
        $this->assertSame(50_000_000, $batch->summary_json['proposed_principal_amount'] + $batch->summary_json['proposed_voluntary_amount']);
    }

    public function test_import_show_list_does_not_send_raw_staging_or_nik(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);

        $batch = CooperativeImportBatch::query()->first();
        $nik = CooperativeLegacyFixture::patternRows()[0]['NIK'];

        $this->actingAs($staff)
            ->get(route('admin.cooperative.imports.show', $batch))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows.data.0.source.name')
                ->missing('rows.data.0.raw_data')
                ->missing('rows.data.0.normalized_data.identity_raw'))
            ->assertDontSee($nik);
    }

    public function test_source_file_is_stored_privately_with_unpredictable_name(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv('legacy-real-name.csv'),
        ]);

        $batch = CooperativeImportBatch::query()->first();
        $this->assertSame('legacy-real-name.csv', $batch->original_filename);
        $this->assertNotSame('legacy-real-name.csv', $batch->stored_path);
        $this->assertDoesNotMatchRegularExpression('/[.]{2}|\\\\|\//', $batch->original_filename);
        $absolute = storage_path('app/private/cooperative-imports/'.$batch->stored_path);
        $this->assertFileExists($absolute);
        $this->assertStringStartsWith(storage_path('app/private'), $absolute);
        $this->assertStringNotContainsString('/public/storage', $absolute);
        $this->assertFileDoesNotExist(public_path('storage/'.$batch->stored_path));
    }

    public function test_staging_json_is_encrypted_at_rest(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);

        $row = CooperativeImportRow::query()->first();
        $stored = DB::table('cooperative_import_rows')->where('id', $row->id)->value('raw_data');
        $this->assertStringContainsString('_encrypted', (string) $stored);
        $this->assertStringNotContainsString('3174010101900001', (string) $stored);
        $this->assertSame('Sari Utami', $row->raw_data['name'] ?? $row->normalized_data['name']);
    }

    public function test_malformed_xlsx_becomes_failed_batch_without_canonical_rows(): void
    {
        $staff = $this->importStaff();

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent('legacy.xlsx', 'this-is-not-xlsx'),
            ])
            ->assertRedirect();

        $batch = CooperativeImportBatch::query()->first();
        $this->assertSame(CooperativeImportBatchStatus::Failed, $batch->status);
        $this->assertDatabaseCount('cooperative_import_rows', 0);
        $this->assertDatabaseCount('participation_entries', 0);
        $this->assertDatabaseCount('cooperative_memberships', 0);
    }

    public function test_duplicate_source_fingerprint_blocks_second_commit(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv('a.csv'),
        ]);
        $first = CooperativeImportBatch::query()->first();
        app(ImportCommitter::class)->commit($first, $staff);

        $renamed = CooperativeLegacyFixture::csv(CooperativeLegacyFixture::patternRows());
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('b-renamed.csv', $renamed),
        ]);
        $second = CooperativeImportBatch::query()->orderByDesc('id')->first();
        $this->assertTrue(
            $second->rows->contains(fn ($row) => in_array('duplicate_committed_source', $row->validation_errors ?? [], true))
        );

        $this->expectException(\RuntimeException::class);
        app(ImportCommitter::class)->commit($second, $staff);
    }

    public function test_conflicting_member_match_signals_need_review(): void
    {
        Member::factory()->create([
            'legal_name' => 'Sari Utami',
            'email' => 'other.sari@example.test',
        ]);
        Member::factory()->create([
            'legal_name' => 'Budi Lain',
            'email' => 'sari.utami@example.test',
        ]);

        $match = (new MemberMatcher)->match([
            'identity_class' => 'other',
            'identity_value' => null,
            'email' => 'sari.utami@example.test',
            'phone_normalized' => null,
            'name' => 'Sari Utami',
        ]);

        $this->assertNull($match['member_id']);
        $this->assertSame('ambiguous', $match['method']->value);
    }

    public function test_name_only_and_duplicate_phone_do_not_auto_bind(): void
    {
        Member::factory()->create(['legal_name' => 'Nama Sama', 'phone' => '081234567800']);
        Member::factory()->create(['legal_name' => 'Nama Sama', 'phone' => '081234567801']);
        Member::factory()->create(['legal_name' => 'Phone Twin A', 'phone' => '081200000001']);
        Member::factory()->create(['legal_name' => 'Phone Twin B', 'phone' => '+6281200000001']);

        $nameOnly = (new MemberMatcher)->match([
            'name' => 'Nama Sama',
            'email' => null,
            'phone_normalized' => null,
            'identity_class' => 'other',
        ]);
        $this->assertNull($nameOnly['member_id']);
        $this->assertSame('ambiguous', $nameOnly['method']->value);

        $phone = (new MemberMatcher)->match([
            'name' => 'Someone Else',
            'email' => null,
            'phone_normalized' => '081200000001',
            'identity_class' => 'other',
        ]);
        $this->assertNull($phone['member_id']);
        $this->assertSame('ambiguous', $phone['method']->value);
    }

    public function test_nik_is_not_used_as_a_match_key(): void
    {
        Member::factory()->create([
            'membership_number' => 'AB2TI/08/I/0005',
            'email' => 'id.match@example.test',
        ]);

        $nik = (new MemberMatcher)->match([
            'identity_class' => 'valid_nik',
            'identity_value' => '3174010101900001',
            'name' => 'Nobody',
            'email' => null,
            'phone_normalized' => null,
        ]);
        $this->assertNull($nik['member_id']);

        $identifier = (new MemberMatcher)->match([
            'identity_class' => 'ab2ti_identifier',
            'identity_value' => 'AB2TI/08/I/0005',
            'name' => null,
            'email' => null,
            'phone_normalized' => null,
        ]);
        $this->assertNotNull($identifier['member_id']);
    }

    public function test_reversal_is_idempotent_and_updates_public_stats(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();

        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);
        $batch = CooperativeImportBatch::query()->first();
        app(ImportCommitter::class)->commit($batch, $staff);

        config(['cooperative.psn_legacy_reconciliation_complete' => true]);

        $stats = app(CooperativePublicStats::class)->toArray();
        $this->assertGreaterThan(0, $stats['total_psn_shares']);

        $openingIds = ParticipationEntry::query()->pluck('id')->all();
        app(ImportReverser::class)->reverse($batch->fresh(), $staff);

        $this->assertSame(CooperativeImportBatchStatus::Reversed, $batch->fresh()->status);
        $this->assertSame($openingIds, ParticipationEntry::query()->whereIn('id', $openingIds)->pluck('id')->all());
        $this->assertSame(0, app(CooperativePublicStats::class)->toArray()['total_psn_shares']);
        $this->assertSame(0, app(CooperativePublicStats::class)->toArray()['psn_participants']);

        $this->expectException(\RuntimeException::class);
        app(ImportReverser::class)->reverse($batch->fresh(), $staff);
    }

    public function test_unsafe_reversal_is_blocked_after_later_adjustment(): void
    {
        $staff = $this->importStaff();
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $csv = CooperativeLegacyFixture::csv([CooperativeLegacyFixture::patternRows()[0]]);
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('one.csv', $csv),
        ]);
        $batch = CooperativeImportBatch::query()->first();
        app(ImportCommitter::class)->commit($batch, $staff);

        $opening = ParticipationEntry::query()->first();
        ParticipationEntry::factory()->create([
            'business_participation_id' => $opening->business_participation_id,
            'entry_type' => ParticipationEntryType::Adjustment,
            'units' => -1,
            'unit_value' => 1_000_000,
            'amount' => -1_000_000,
            'source_type' => 'staff',
            'import_batch_id' => null,
            'notes' => 'later independent adjustment',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('later independent');
        app(ImportReverser::class)->reverse($batch->fresh(), $staff);
    }

    public function test_viewer_cannot_commit_or_reverse(): void
    {
        $manager = $this->importStaff();
        $viewer = $this->userWithPermissions(['cooperative.view']);
        Member::factory()->create(['email' => 'sari.utami@example.test']);

        $this->actingAs($manager)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);
        $batch = CooperativeImportBatch::query()->first();

        $this->actingAs($viewer)
            ->post(route('admin.cooperative.imports.commit', $batch), ['confirm' => '1'])
            ->assertForbidden();

        app(ImportCommitter::class)->commit($batch, $manager);

        $this->actingAs($viewer)
            ->post(route('admin.cooperative.imports.reverse', $batch->fresh()))
            ->assertForbidden();
    }

    public function test_commit_requires_explicit_confirmation(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);
        $batch = CooperativeImportBatch::query()->first();

        $this->actingAs($staff)
            ->post(route('admin.cooperative.imports.commit', $batch))
            ->assertSessionHasErrors('confirm');
        $this->assertDatabaseCount('participation_entries', 0);
    }

    public function test_sequential_double_commit_does_not_duplicate_canonical_rows(): void
    {
        $staff = $this->importStaff();
        $this->seedReadyMembers();
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => $this->uploadedCsv(),
        ]);
        $batch = CooperativeImportBatch::query()->first();
        app(ImportCommitter::class)->commit($batch, $staff);

        try {
            app(ImportCommitter::class)->commit($batch->fresh(), $staff);
            $this->fail('Second commit should fail');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('participation_entries', 4);
        $this->assertDatabaseCount('cooperative_memberships', 4);
    }

    public function test_synthetic_parse_of_five_hundred_rows_stays_bounded(): void
    {
        $staff = $this->importStaff();
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $member = Member::factory()->create([
                'email' => sprintf('synth.%04d@example.test', $i),
            ]);
            $rows[] = [
                'Nama' => $member->legal_name,
                'Email' => $member->email,
                'Total' => '1.000.000',
                'No. Saham' => sprintf('33-%06d', $i),
            ];
        }

        $before = memory_get_usage(true);
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('synth.csv', CooperativeLegacyFixture::csv($rows)),
        ])->assertRedirect();
        $after = memory_get_usage(true);

        $batch = CooperativeImportBatch::query()->first();
        $this->assertSame(500, $batch->summary_json['total']);
        $this->assertDatabaseCount('participation_entries', 0);
        $this->assertLessThan(64 * 1024 * 1024, $after - $before);
    }

    public function test_workspace_labels_historical_funds_as_source_not_savings_balance(): void
    {
        $this->syncAclFromConfig();
        $account = \App\Models\User::factory()->create();
        $member = Member::factory()->linked($account)->create(['email' => 'sari.utami@example.test']);

        $staff = $this->importStaff();
        $csv = CooperativeLegacyFixture::csv([CooperativeLegacyFixture::patternRows()[0]]);
        $csv = str_replace('sari.utami@example.test', $member->email, $csv);
        $this->actingAs($staff)->post(route('admin.cooperative.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('own.csv', $csv),
        ]);
        app(ImportCommitter::class)->commit(CooperativeImportBatch::query()->first(), $staff);

        $this->actingAs($account)
            ->get(route('account.lapak.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cooperative.psn.has_psn', true)
                ->where('cooperative.psn.units', 50)
                ->has('cooperative.psn.historical_funds')
                ->missing('cooperative.psn.raw_data'))
            ->assertDontSee('Saldo tabungan aktif')
            ->assertDontSee('Saldo Simpanan');
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
