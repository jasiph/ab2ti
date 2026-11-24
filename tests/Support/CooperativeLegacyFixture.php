<?php

namespace Tests\Support;

final class CooperativeLegacyFixture
{
    /**
     * @param  list<array<string, string>>  $rows
     */
    public static function csv(array $rows): string
    {
        $headers = [
            'Nama',
            'NIK',
            'Email',
            'Telepon',
            'Alamat',
            'Simpanan Pokok',
            'Simpanan Sukarela',
            'Total',
            'No. Saham',
            'Tanggal',
            'Ket',
            'Keterangan',
        ];

        $lines = [implode(',', $headers)];

        foreach ($rows as $row) {
            $ordered = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                $ordered[] = str_contains((string) $value, ',') ? '"'.$value.'"' : $value;
            }
            $lines[] = implode(',', $ordered);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<array<string, string>>
     */
    public static function patternRows(): array
    {
        return [
            [
                'Nama' => 'Sari Utami',
                'NIK' => '3174010101900001',
                'Email' => 'sari.utami@example.test',
                'Telepon' => '081234567890',
                'Alamat' => 'Jl. Fixture No. 1',
                'Simpanan Pokok' => '1.000.000',
                'Simpanan Sukarela' => '49.000.000',
                'Total' => '50.000.000',
                'No. Saham' => '32-1-000181 (50)',
                'Tanggal' => '26/09/2023',
                'Ket' => 'USM',
                'Keterangan' => 'Pembayaran tunai fiktif',
            ],
            [
                'Nama' => 'Anton Setiawan',
                'NIK' => '3174010101910002',
                'Email' => 'anton.setiawan@example.test',
                'Telepon' => '081234567891',
                'Alamat' => 'Jl. Fixture No. 2',
                'Simpanan Pokok' => '',
                'Simpanan Sukarela' => '',
                'Total' => '1.000.000',
                'No. Saham' => '33-000231',
                'Tanggal' => '26/09/2023',
                'Ket' => 'Online',
                'Keterangan' => '',
            ],
            [
                'Nama' => 'Darto Prakoso',
                'NIK' => '3174010101920003',
                'Email' => 'darto.prakoso@example.test',
                'Telepon' => '081234567892',
                'Alamat' => 'Jl. Fixture No. 3',
                'Simpanan Pokok' => '',
                'Simpanan Sukarela' => '',
                'Total' => '1.000.000',
                'No. Saham' => '33-000232',
                'Tanggal' => '26-27/09/2023',
                'Ket' => 'Puput',
                'Keterangan' => '',
            ],
            [
                'Nama' => 'Dukut Raharjo',
                'NIK' => '3174010101930004',
                'Email' => 'dukut.raharjo@example.test',
                'Telepon' => '081234567893',
                'Alamat' => 'Jl. Fixture No. 4',
                'Simpanan Pokok' => '1.000.000',
                'Simpanan Sukarela' => '1.000.000',
                'Total' => '2.000.000',
                'No. Saham' => '33-000233 (2)',
                'Tanggal' => '26/09/2023',
                'Ket' => '',
                'Keterangan' => '',
            ],
        ];
    }
}
