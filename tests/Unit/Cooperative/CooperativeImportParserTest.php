<?php

namespace Tests\Unit\Cooperative;

use App\Enums\CooperativeIdentityClass;
use App\Support\Cooperative\Import\DateRangeParser;
use App\Support\Cooperative\Import\IdentityClassifier;
use App\Support\Cooperative\Import\MoneyParser;
use App\Support\Cooperative\Import\PhoneNormalizer;
use App\Support\Cooperative\Import\ShareReferenceParser;
use Tests\TestCase;

class CooperativeImportParserTest extends TestCase
{
    public function test_money_parser_accepts_indonesian_grouping_blank_and_dash(): void
    {
        $parser = new MoneyParser;

        $this->assertSame(1_000_000, $parser->parse('1.000.000')['amount']);
        $this->assertSame(49_000_000, $parser->parse('49.000.000')['amount']);
        $this->assertTrue($parser->parse('-')['blank']);
        $this->assertSame(0, $parser->parse('-')['amount']);
        $this->assertTrue($parser->parse('')['blank']);
        $this->assertSame(0, $parser->parse(null)['amount']);
        $this->assertSame(1_000_000, $parser->parse('Rp 1.000.000')['amount']);
        $this->assertSame(1_000_000, $parser->parse(' 1.000.000 ')['amount']);
        $this->assertSame(1_000_000, $parser->parse('1.000.000,00')['amount']);
        $this->assertSame(1_000_000, $parser->parse('1,000,000')['amount']);
        $this->assertSame(0, $parser->parse('0')['amount']);
        $this->assertFalse($parser->parse('1,000.50')['parseable']);
        $this->assertFalse($parser->parse('-1.000.000')['parseable']);
        $this->assertFalse($parser->parse('abc')['parseable']);
    }

    public function test_date_parser_keeps_single_dates_and_ranges(): void
    {
        $parser = new DateRangeParser;

        $single = $parser->parse('26/09/2023');
        $this->assertTrue($single['parseable']);
        $this->assertSame('2023-09-26', $single['start']);
        $this->assertNull($single['end']);

        $range = $parser->parse('26-27/09/2023');
        $this->assertTrue($range['parseable']);
        $this->assertSame('2023-09-26', $range['start']);
        $this->assertSame('2023-09-27', $range['end']);

        $spaced = $parser->parse('26 - 27 / 09 / 2023');
        $this->assertTrue($spaced['parseable']);
        $this->assertSame('2023-09-26', $spaced['start']);
        $this->assertSame('2023-09-27', $spaced['end']);

        $uncertain = $parser->parse('minggu lalu');
        $this->assertFalse($uncertain['parseable']);
        $this->assertTrue($uncertain['uncertain']);
        $this->assertNull($uncertain['start']);

        $this->assertFalse($parser->parse('13/13/2023')['parseable']);
    }

    public function test_share_reference_parser_splits_explicit_units(): void
    {
        $parser = new ShareReferenceParser;

        $explicit = $parser->parse('32-1-000181 (50)');
        $this->assertSame('32-1-000181', $explicit['share_reference']);
        $this->assertSame(50, $explicit['explicit_units']);

        $two = $parser->parse('33-000233 (2)');
        $this->assertSame('33-000233', $two['share_reference']);
        $this->assertSame(2, $two['explicit_units']);

        $bare = $parser->parse('33-000231');
        $this->assertSame('33-000231', $bare['share_reference']);
        $this->assertNull($bare['explicit_units']);

        $whitespace = $parser->parse('  33-000231 (1)  ');
        $this->assertSame('33-000231', $whitespace['share_reference']);
        $this->assertSame(1, $whitespace['explicit_units']);

        $unknown = $parser->parse('saham lama???');
        $this->assertFalse($unknown['parseable']);
        $this->assertFalse($parser->parse('33-000231 (1) (2)')['parseable']);
        $this->assertFalse($parser->parse('33-000231 (x)')['parseable']);
        $this->assertFalse($parser->parse('33-000231 extra')['parseable']);
    }

    public function test_header_mapper_normalizes_spacing_bom_and_blocks_unusable_files(): void
    {
        $mapper = new \App\Support\Cooperative\Import\HeaderMapper;

        $map = $mapper->map(["\xEF\xBB\xBFNama", 'Tanggal   Terdaftar Anggota', 'Total', '']);
        $this->assertSame(0, $map['name']);
        $this->assertSame(1, $map['date']);
        $this->assertSame(2, $map['total']);

        $rp = $mapper->map(['Nama', 'Simpanan Pokok (Rp)', 'Total (Rp)', 'No. Saham']);
        $this->assertSame(0, $rp['name']);
        $this->assertSame(1, $rp['principal']);
        $this->assertSame(2, $rp['total']);
        $this->assertSame(3, $rp['share']);

        $newline = $mapper->map(["Tanggal\nTerdaftar Anggota", 'Nama', 'Total']);
        $this->assertSame(0, $newline['date']);
        $this->assertTrue($mapper->isCanonicalHeaderRow(['No', 'Nama', 'Total (Rp)', 'No. Saham']));

        $this->expectException(\App\Support\Cooperative\Import\ImportException::class);
        $mapper->map(['Nama', 'Nama', 'Total']);
    }

    public function test_header_mapper_blocks_files_without_core_financial_headers(): void
    {
        $mapper = new \App\Support\Cooperative\Import\HeaderMapper;

        $this->expectException(\App\Support\Cooperative\Import\ImportException::class);
        $mapper->assertCoreHeaders($mapper->map(['Nama', 'Keterangan']));
    }

    public function test_identity_classifier_separates_nik_and_ab2ti_identifier(): void
    {
        $classifier = new IdentityClassifier;

        $nik = $classifier->classify('3174010101900001');
        $this->assertSame(CooperativeIdentityClass::ValidNik, $nik['class']);

        $legacy = $classifier->classify('AB2TI/08/I/0005');
        $this->assertSame(CooperativeIdentityClass::Ab2tiIdentifier, $legacy['class']);

        $other = $classifier->classify('KTP-LAMA');
        $this->assertSame(CooperativeIdentityClass::Other, $other['class']);
    }

    public function test_phone_normalizer_converts_indonesia_prefixes(): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertSame('081234567890', $normalizer->normalize('0812-3456-7890')['normalized']);
        $this->assertSame('081234567890', $normalizer->normalize('+62 81234567890')['normalized']);
        $this->assertSame('081234567890', $normalizer->normalize('6281234567890')['normalized']);
        $this->assertFalse($normalizer->normalize('not-a-phone')['parseable']);
    }

    public function test_source_row_classifier_keeps_numbered_members_and_ignores_footers(): void
    {
        $classifier = new \App\Support\Cooperative\Import\SourceRowClassifier;

        $this->assertTrue($classifier->isMemberRow([
            'row_no' => 1,
            'name' => 'Sari Utami',
            'email' => 'sari.fixture@example.test',
        ]));
        $this->assertFalse($classifier->isMemberRow([
            'row_no' => '',
            'name' => 'Laki-laki',
        ]));
        $this->assertFalse($classifier->isMemberRow([
            'row_no' => null,
            'name' => 'Total',
        ]));
        $this->assertFalse($classifier->isMemberRow([
            'row_no' => 'Perempuan',
            'name' => 'Perempuan',
        ]));
    }
}
