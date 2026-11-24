<?php

namespace App\Support\Cooperative\Import;

final class PhoneNormalizer
{
    /**
     * Normalize an Indonesian phone for exact matching.
     *
     * @return array{raw: ?string, normalized: ?string, parseable: bool}
     */
    public function normalize(mixed $value): array
    {
        $raw = is_string($value) || is_numeric($value) ? trim((string) $value) : null;

        if ($raw === null || $raw === '' || $raw === '-') {
            return [
                'raw' => $raw,
                'normalized' => null,
                'parseable' => true,
            ];
        }

        $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';
        $digits = str_replace('+', '', $digits);

        if ($digits === '') {
            return [
                'raw' => $raw,
                'normalized' => null,
                'parseable' => false,
            ];
        }

        if (str_starts_with($digits, '62') && ! str_starts_with($digits, '620')) {
            $digits = '0'.substr($digits, 2);
        }

        if (! str_starts_with($digits, '0') && strlen($digits) >= 9) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) < 9 || strlen($digits) > 15) {
            return [
                'raw' => $raw,
                'normalized' => null,
                'parseable' => false,
            ];
        }

        return [
            'raw' => $raw,
            'normalized' => $digits,
            'parseable' => true,
        ];
    }
}
