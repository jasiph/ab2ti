<?php

namespace App\Support\Cooperative\Import;

final class HeaderMapper
{
    /**
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'name' => ['nama', 'name', 'nama anggota', 'nama lengkap'],
        'identity' => ['nik', 'no. identitas', 'no identitas', 'identitas', 'ktp'],
        'email' => ['email', 'e-mail', 'surel'],
        'phone' => ['telepon', 'telp', 'phone', 'hp', 'no. hp', 'no hp', 'no. telepon', 'no telepon'],
        'address' => [
            'alamat',
            'alamat lengkap ktp',
            'alamat ktp',
            'alamat lengkap',
        ],
        'row_no' => ['no', 'no.', 'nomor', 'no urut'],
        'principal' => ['simpanan pokok', 'pokok', 'simpanan pokok (rp)'],
        'voluntary' => ['simpanan sukarela', 'sukarela', 'simpanan sukarela (rp)'],
        'total' => ['total', 'jumlah', 'total (rp)'],
        'share' => ['no. saham', 'no saham', 'nomor saham', 'saham'],
        'date' => [
            'tanggal',
            'tgl',
            'date',
            'tgl bergabung',
            'tanggal bergabung',
            'tanggal terdaftar',
            'tanggal terdaftar anggota',
        ],
        'legacy_label' => ['ket'],
        'legacy_notes' => ['keterangan', 'catatan'],
    ];

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    public function map(array $headers): array
    {
        $map = [];
        $seen = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalize((string) $header);

            if ($normalized === '') {
                continue;
            }

            $canonical = $this->canonicalFromNormalized($normalized);

            if ($canonical === null) {
                continue;
            }

            if (isset($seen[$canonical])) {
                throw ImportException::duplicateHeaders();
            }

            $seen[$canonical] = true;
            $map[$canonical] = $index;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $map
     */
    public function assertCoreHeaders(array $map): void
    {
        if (! isset($map['name'])) {
            throw ImportException::missingCoreHeaders();
        }

        if (! isset($map['total']) && ! isset($map['principal']) && ! isset($map['share'])) {
            throw ImportException::missingCoreHeaders();
        }
    }

    /**
     * @param  list<mixed>  $cells
     */
    public function isCanonicalHeaderRow(array $cells): bool
    {
        $headers = array_map(
            fn (mixed $cell): string => trim((string) $cell),
            $cells,
        );

        try {
            $map = $this->map($headers);
        } catch (ImportException) {
            return false;
        }

        return isset($map['name'])
            && (isset($map['total']) || isset($map['principal']) || isset($map['share']));
    }

    public function canonical(string $header): ?string
    {
        return $this->canonicalFromNormalized($this->normalize($header));
    }

    private function canonicalFromNormalized(string $normalized): ?string
    {
        foreach (self::ALIASES as $canonical => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    public function normalize(string $header): string
    {
        $withoutBom = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $flattened = str_replace(["\n", "\r", "\t", "\u{00A0}"], ' ', $withoutBom);
        $collapsed = preg_replace('/\s+/', ' ', trim($flattened)) ?? trim($flattened);
        $lower = mb_strtolower($collapsed);

        return preg_replace('/\s*\(rp\)\s*$/u', '', $lower) ?? $lower;
    }
}
