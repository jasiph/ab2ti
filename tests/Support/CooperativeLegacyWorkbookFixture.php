<?php

namespace Tests\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class CooperativeLegacyWorkbookFixture
{
    /**
     * Sanitized multi-sheet workbook reproducing the real Cooperative layout
     * without real names, NIK, phones, emails, or addresses.
     */
    public static function path(string $filename = 'legacy-anggota-ok.xlsx'): string
    {
        $directory = storage_path('app/private');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $writer = new Writer;
        $writer->openToFile($path);

        $anggota = $writer->getCurrentSheet();
        $anggota->setName('A.Anggota OK');
        self::writeAnggotaSheet($writer);

        $investasi = $writer->addNewSheetAndMakeItCurrent();
        $investasi->setName('B.Investasi OK');
        self::writeInvestasiSheet($writer);

        $lama = $writer->addNewSheetAndMakeItCurrent();
        $lama->setName('Anggota Lama');
        $writer->addRow(Row::fromValues(['Catatan lama', 'bukan header koperasi']));

        $sheet1 = $writer->addNewSheetAndMakeItCurrent();
        $sheet1->setName('Sheet1');
        $writer->addRow(Row::fromValues(['scratch']));

        $list = $writer->addNewSheetAndMakeItCurrent();
        $list->setName('List Anggota');
        $list->setIsVisible(false);
        $writer->addRow(Row::fromValues(['Nama', 'Email', 'Total', 'No. Saham']));
        $writer->addRow(Row::fromValues([
            'Hidden Fixture',
            'hidden.fixture@example.test',
            '1.000.000',
            '33-HIDDEN',
        ]));

        $kwitansi = $writer->addNewSheetAndMakeItCurrent();
        $kwitansi->setName('Kwitansi');
        $kwitansi->setIsVisible(false);
        $writer->addRow(Row::fromValues(['Nomor', 'Uraian']));

        $writer->close();

        return $path;
    }

    public static function uploadedFile(string $filename = 'legacy-anggota-ok.xlsx'): \Illuminate\Http\UploadedFile
    {
        $path = self::path($filename);

        return new \Illuminate\Http\UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private static function writeAnggotaSheet(Writer $writer): void
    {
        $writer->addRow(Row::fromValues(['Koperasi Pusat AB2TI']));
        $writer->addRow(Row::fromValues(['Daftar anggota uji (sanitized)']));
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(self::anggotaHeaders()));

        foreach (self::anggotaMemberRows() as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->addRow(Row::fromValues(['', 'Laki-laki']));
        $writer->addRow(Row::fromValues(['', 'Perempuan']));
        $writer->addRow(Row::fromValues(['', 'Total', '', '', '', '', '', '', '', '', '', '', '4.000.000']));
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['formatted trailing']));
    }

    private static function writeInvestasiSheet(Writer $writer): void
    {
        $writer->addRow(Row::fromValues(['Nama', 'Email', 'Total', 'No. Saham']));
        $writer->addRow(Row::fromValues([
            'Investasi Fixture',
            'investasi.fixture@example.test',
            '1.000.000',
            '33-INV-001',
        ]));
    }

    /**
     * @return list<string>
     */
    public static function anggotaHeaders(): array
    {
        return [
            'No',
            'Nama',
            'Jenis Kelamin',
            'NIK',
            'No. HP',
            'Email',
            'Pendidikan Terakhir',
            'Jurusan',
            'Asal Daerah',
            'Alamat Lengkap KTP',
            'Alamat Domisili/Pengiriman Dokumen',
            'Tanggal Terdaftar Anggota',
            'Simpanan Pokok (Rp)',
            'Simpanan Sukarela (Rp)',
            'Total (Rp)',
            'Tanggal Transfer',
            'Ket',
            'Keterangan',
            'Sudah dilakukan pengiriman (Ya/Belum)',
            'No. Saham',
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    public static function anggotaMemberRows(): array
    {
        return [
            [1, 'Sari Fixture', 'P', 'AB2TI/08/I/0005', '081200000001', 'sari.fixture@example.test', '', '', '', 'Jl. Fixture 1', 'Jl. Domisili 1', '26/09/2023', '1.000.000', '', '1.000.000', '', '', '', 'Ya', '32-1-000181 (1)'],
            [2, 'Anton Fixture', 'L', '3174010101910002', '081200000002', 'anton.fixture@example.test', '', '', '', 'Jl. Fixture 2', 'Jl. Domisili 2', '26/09/2023', '', '', '1.000.000', '', '', '', 'Ya', '33-000231'],
            [3, 'Fraction Fixture', 'L', '3174010101920003', '081200000003', 'fraction.fixture@example.test', '', '', '', 'Jl. Fixture 3', 'Jl. Domisili 3', '26/09/2023', '', '', '1.500.000', '', '', '', 'Belum', '33-000299'],
            [4, 'Contact Gap', 'P', 'KTP-LAMA', '081200000004', 'gap.fixture@example.test', '', '', '', '', '', '26/09/2023', '', '', '1.000.000', '', '', '', 'Ya', '33-000300'],
        ];
    }
}
